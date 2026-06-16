| Project | Focus | State | Progress | PHP Tests | Mapped Upstream | Unmapped | Next Gate | Commit |
| --- | --- | --- | ---: | ---: | --- | ---: | --- | --- |
| [libsqlite](lanes/libsqlite/lane-status.json) | Primary | PHP green, upstream gap | 99.6% | 6,290,284 pass / 0 fail | [1,589 / 1,589 (100.0%)](lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json) | 0 | No local blocker | 16d8081 |
| [LightningCSS](lanes/lightningcss/lane-status.json) | Active | PHP green, upstream gap | 99.8% | 9,280 pass / 0 fail | [2,445 / 3,532 (69.2%)](lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json) | 1,087 | Full upstream runner closure is partial: bounded Rust media test and... | pending isolate... |
| [gitoxide](lanes/gitoxide/lane-status.json) | Active | High coverage | 98.8% | 11,183 pass / 0 fail | [1,821 / 2,886 (63.1%)](lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json) | 1,065 | Cargo workspace blocked by sparse target files | 29e9ab4 |
| [markerPDF](lanes/markerpdf/lane-status.json) | Active | PHP green, upstream gap | 100.0% | 3,621 pass / 0 fail | [763 / 78 (978.2%)](lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json) | 0 | No GPU/model execution will be run for markerPDF under current user d... | pending fast ba... |
| [Readability/content rewrite engine](lanes/readability/lane-status.json) | Backlog | Active port | 85.0% | 154 pass / 0 fail | [1,578 / 1,984 (79.5%)](lanes/readability/UPSTREAM_TEST_MANIFEST.json) | 406 | No local blocker | cd2e8a0 |
| [pandoc](lanes/pandoc/lane-status.json) | Backlog | High coverage | 96.0% | 6,415 pass / 0 fail | [6,405 / 2,276 (281.4%)](lanes/pandoc/UPSTREAM_TEST_MANIFEST.json) | 0 | Markdown writer auto HTML table fallback validation passed after rebase onto current main | pandoc-markdown-writer-auto-html-table-surge |
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
| Partial native PHP input support to finish | 21 |
| Unsupported native PHP input tokens to implement | 28 |

Focused test counts below are evidence counters, not a strict remaining-test burn-down. Percentages above 100% mean the local PHP tests are more granular than the upstream case counter available for that family; they do not claim upstream runner parity.

### ODT Layout-Cache Package Sidecar Policy (2026-06-16)

Bounded native PHP ODF/ODT package-reader coverage advances by one metadata-only sidecar slice for `layout-cache` package parts. `OdfReader` and `OpenDocumentPackage` now classify declared and undeclared layout-cache sidecars, block package byte exposure, keep them out of document media handoff, preserve manifest/package inventory role provenance, and report missing, encrypted, invalid-media-type, and undeclared review issues without invoking Pandoc, office suites, zip/unzip, ZipArchive, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

Verification passed `php -l` for `OdfReader.php`, `OpenDocumentPackage.php`, `OdfReaderTest.php`, and `OpenDocumentPackageTest.php`; focused package tests passed (`2` files, `6,691` assertions, `0` failures); focused ODF/ODT gate passed (`5` files, `7,022` assertions, `0` failures); full `lanes/pandoc/tests` passed (`195` files, `169,995` assertions, `0` failures); JSON validation; and `git diff --check`. ODF/ODT evidence is now 86 local mapped cases against the 20 accepted static upstream ODF/ODT rows, with 0 remaining critical ODF/ODT gaps.

### Markdown Writer Auto HTML Table Fallback Surge (2026-06-15)

Bounded native PHP Markdown writer coverage advances by 60 upstream-mapped Markdown/CommonMark/GFM table/caption/span cases after rebase onto current main `125ba7381c`. `MarkdownWriter` adds opt-in `htmlTableAutoFallback` behavior for non-pipe-preservable table structures while preserving default pipe-table output and explicit `data-pandoc-writer` HTML fallback behavior.

Validation passed after conflict resolution: `php -l` for `MarkdownWriter.php` and `MarkdownWriterTableAutoHtmlSurgeTest.php`; focused `MarkdownWriterTableAutoHtmlSurgeTest.php` (`1` file, `199` assertions, `0` failures); focused Markdown writer table group (`8` files, `10,069` assertions, `0` failures); full `lanes/pandoc/tests` (`76` files, `106,444` assertions, `0` failures); JSON validation; `git diff --check`; and exact conflict-marker scan. No Pandoc binary, cmark/commonmark runner, Cabal/Haskell runner, browser renderer, Node tooling, online service, live provider test, live-service provider test, or external validator is invoked.

Markdown/CommonMark/GFM evidence is now 2,557 local mapped cases against the 1,096 accepted static upstream Markdown rows. The native PHP Markdown reader/writer remains partial pending broader metadata/raw/extension parity.

### Markdown Writer List Style/Code Surge (2026-06-15)

Bounded native PHP Markdown writer coverage advances by 50 upstream-mapped Markdown/CommonMark/GFM list/code cases after rebase onto current main `ff183ef1a3`. `MarkdownWriter` emits Pandoc default ordered markers (`#.` and `#)`), numbered example markers with optional labels, and compact indented code-only list items for bullet, decimal, default, example, alpha, and roman markers while keeping attributed or forced code blocks fenced and preserving the current inline/link/escape completion behavior.

Validation passed after conflict resolution: `php -l` for `MarkdownWriter.php`, `MarkdownWriterBlockListCodeSurgeTest.php`, and `MarkdownReaderTest.php`; focused `MarkdownWriterBlockListCodeSurgeTest.php` (`1` file, `425` assertions, `0` failures); focused Markdown writer/readback cluster (`6` files, `11,099` assertions, `0` failures); full `lanes/pandoc/tests` (`75` files, `106,245` assertions, `0` failures); JSON validation; `git diff --check`; and exact conflict-marker scan. No Pandoc binary, cmark/commonmark runner, Cabal/Haskell runner, browser renderer, Node tooling, online service, live provider test, live-service provider test, or external validator is invoked.

Markdown/CommonMark/GFM evidence is now 2,497 local mapped cases against the 1,096 accepted static upstream Markdown rows. The native PHP Markdown reader/writer remains partial pending broader metadata/raw/extension parity.
### Markdown Writer Inline Link Escape Completion Surge (2026-06-15)

Bounded native PHP Markdown writer coverage advances by 60 upstream-mapped inline/link/escape completion cases after rebase onto current main `3df651d289`. `MarkdownWriter` escapes citation-looking `@` markers after whitespace, punctuation, softbreaks, hardbreaks, and nested inline labels; only emits compact autolinks for valid 2-32 character URI schemes or valid mailto email addresses with matching `.uri`/`.email` classes; and serializes inline attribute identifiers/values on one line with escaped whitespace/control/braces while preserving current figure-caption, native-div, and URL-normalization coverage.

Verification passed `php -l` for `MarkdownWriter.php` and `MarkdownWriterInlineLinkEscapeCompletionSurgeTest.php`; focused `MarkdownWriterInlineLinkEscapeCompletionSurgeTest.php` plus `MarkdownWriterInlinesSurgeTest.php` plus `MarkdownWriterInlineSurgeTest.php` passed (`3` files, `240` assertions, `0` failures); ordered-marker regression gate passed (`3` files, `784` assertions, `0` failures); Markdown-focused suite passed (`18` files, `19,241` assertions, `0` failures); full `lanes/pandoc/tests` passed (`75` files, `106,135` assertions, `0` failures); JSON validation; `git diff --check`; and exact conflict-marker scan. No Pandoc binary, cmark/commonmark runner, Cabal/Haskell runner, browser renderer, Node tooling, online service, live provider test, live-service provider test, or external validator was invoked.

Markdown/CommonMark/GFM evidence is now 2,447 local mapped cases against the 1,096 accepted static upstream Markdown rows. The native PHP Markdown reader/writer remains partial pending broader metadata/raw/extension parity.
### Markdown Figure Caption Surge (2026-06-15)

Bounded native PHP Markdown reader coverage advances by 60 additional upstream-mapped figure caption cases after rebase onto current main `c12e4c4023`. `MarkdownReader` preserves leading and trailing explicit captions for standalone inline, attributed, and reference images, including source marker metadata, short captions, multiline caption inlines, figure/html attributes, MarkdownWriter output, and WordPress figure handoff while preserving the 54 figure-caption cases already on main plus current native-div and URL-normalization coverage.

Verification passed `php -l` for `MarkdownReader.php` and `MarkdownReaderFigureCaptionSurgeTest.php`; focused `MarkdownReaderFigureCaptionSurgeTest.php` passed (`1` file, `3,019` assertions, `0` failures); focused `MarkdownReaderFigureCaptionSurgeTest.php` plus `MarkdownReaderTablesSurgeTest.php` plus `MarkdownReaderTest.php` plus `MarkdownWriterTablesSurgeTest.php` passed (`4` files, `10,721` assertions, `0` failures); full `lanes/pandoc/tests` passed (`74` files, `106,075` assertions, `0` failures). No Pandoc binary, cmark/commonmark runner, Cabal/Haskell runner, browser renderer, Node tooling, online service, live provider test, live-service provider test, or external validator was invoked.

Markdown/CommonMark/GFM evidence is now 2,387 local mapped cases against the 1,096 accepted static upstream Markdown rows. The native PHP Markdown reader remains partial pending broader metadata/raw/extension parity.
### Markdown Native Div Extension Surge (2026-06-15)

Bounded native PHP Markdown reader coverage advances by 50 upstream-mapped `native_divs` metadata/raw extension cases after rebase onto current main `cc87fc6ba3`. `MarkdownReader` preserves attributed HTML div blocks with ids, normalized classes, data/ARIA/lang/dir/title/role/translate/xml:lang attributes, quote-aware `>` and entity attribute values, MarkdownWriter fenced-div output, and WordPress block handoff while preserving current URL-normalization, native-span, raw-attribute, reference-definition, table-caption, bare-autolink, block/list/code, writer inline/link/escape, and emoji alias coverage.

Verification passed `php -l` for `MarkdownReader.php` and `MarkdownReaderMetadataRawExtensionSurgeTest.php`; focused `MarkdownReaderMetadataRawExtensionSurgeTest.php` plus `MarkdownReaderTest.php` passed (`2` files, `10,079` assertions, `0` failures); full `lanes/pandoc/tests` passed (`74` files, `105,433` assertions, `0` failures). No Pandoc binary, cmark/commonmark runner, Cabal/Haskell runner, browser renderer, Node tooling, online service, live provider test, live-service provider test, or external validator was invoked.

Markdown/CommonMark/GFM evidence is now 2,327 local mapped cases against the 1,096 accepted static upstream Markdown rows. The native PHP Markdown reader remains partial pending broader metadata/raw/extension parity.
### ODT Mimetype Local Header Update (2026-06-15)

Bounded native PHP ODF/ODT package-reader coverage advanced by three compact mimetype preflight cases after rebase onto current main `f6a43baa31`. `OpenDocumentPackage` now validates the ODT mimetype entry against ZIP local-header order, rejects mimetype local-header extra fields before package exposure, and exposes stored-first mimetype provenance in compact package review metadata without invoking Pandoc, office suites, zip/unzip, ZipArchive, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

Verification passed `php -l` for `OpenDocumentPackage.php` and `OpenDocumentPackageTest.php`; focused `OpenDocumentPackageTest.php` passed (`1` file, `1,546` assertions, `0` failures); focused ODF/ODT gate passed (`5` files, `6,756` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `86,330` assertions, `0` failures). ODF/ODT evidence is now 85 local mapped cases against the 20 accepted static upstream ODF/ODT rows, with 0 remaining critical ODF/ODT gaps.

| Input family | In-scope input tokens | Current input status | Local passing | Upstream denominator | Remaining input work |
| --- | --- | --- | ---: | ---: | --- |
| Markdown/CommonMark/GFM | `commonmark`, `commonmark_x`, `gfm`, `markdown`, `markdown_github`, `markdown_mmd`, `markdown_phpextra`, `markdown_strict` | partial | 2,447 | 1,096 | Reference-label escape/entity normalization, URL control-byte normalization, native span and native div extension handoff, Markdown emoji aliases, YAML metadata alias review summaries, generic raw HTML serialization, HTML list item attributes, math attribute round-trip, escaped fenced-Div attributes, fenced Div section-reference boundaries, nested-list fixture round-trip, definition-list term-group handoff, table-cell note placement, and header section Div mapping are covered; complete extension and variant parity. |
| HTML/XML/JATS DOM | `html`, `xml`, `jats`, and `bits` partial | partial | 298 | 29 | Object association provenance, image-map association and area-geometry diagnostics, XML/HTML5 raw-text boundary diagnostics, XML/HTML5 template/noscript boundary handling, XML/HTML5 nested table foster-parenting, XML/JATS/BITS direct input registry routing, JATS/BITS relationship diagnostics, inline xref local back-reference and unsupported target diagnostics, ref-list bibliography diagnostics, reference identifier diagnostics, title metadata propagation, figure label/caption/title metadata diagnostics, figure caption issue/xref metadata diagnostics, front-matter, body, table-body, and back-matter reference diagnostics review packets, and DocBook bibliography/reference, inline media alt, structure, section metadata, structural/media review packets, and media role/caption diagnostics are covered; finish HTML5 tree construction and full XML/JATS/BITS reader parity. |
| JSON/native AST | `json`, `native` | partial | 87 | 252 | Empty MetaMap constructor provenance, single-wrapped metadata constructor payloads, single-wrapped block/inline constructor payload lists, LineBreak summary constructor semantics, single-wrapped table integer helper payloads, styled inline constructor sidecars, legacy table width constructor sidecars, Figure child block payload preservation, nested metadata payload preservation, mixed metadata block-container stress coverage, mixed Figure link/raw/code handoff, citation prefix/suffix payload preservation, raw HTML alias preservation, table attribute handoff, table ColSpec sidecars, mixed block-container and table caption/cell flushing, nullary helper payload validation, task-list checkbox sidecars, note label sidecars, definition-list term handoff, and fixture writer handoff are preserved; complete broader JSON/native AST constructor coverage. |
| DOCX/OpenXML | `docx` | partial | 95 | 35 | Finish remaining direct WordprocessingML/package reader parity; section-property review metadata, subdocument diagnostics, note/comment relationship diagnostics, and custom XML properties schema diagnostics are covered. |
| EPUB/EPUB3 | `epub` | partial | 83 | 9 | OPF package root authoring reports, EPUB NCX navigation selection handoff, OPF package/manifest/spine authoring attribute reports, OPF package prefix reports, package identity reports, duplicate spine idref diagnostics, direct manifest suffix diagnostics, manifest resource-property ZIP provenance, skipped spine-entry reporting, XHTML definition-list handoff, direct XHTML table ingestion, OPF collection hierarchy review, OPF metadata item/report review, XHTML table-section review metadata, compact nav/NCX label provenance, NCX hierarchy diagnostics, nav fragment target diagnostics, and nav document section diagnostics are covered; finish broader EPUB package reader parity. |
| ODF/ODT/OpenDocument | `odt` | ship-ready | 86 | 20 | 0 critical gaps for native PHP ODT import; compact mimetype local-header placement and extra-field preflight, compact raw ZIP name provenance, compact manifest custom attribute collision provenance, manifest media-family classification including generic octet-stream package-path fallback, preferred-view-mode token diagnostics, aggregate manifest encryption review buckets, rich manifest encryption method summaries across blocked package parts, package signature and layout-cache sidecar metadata-only policies, sidecar package byte-exposure ordering, and handoff ordering across missing package parts plus unsupported-compression byte blocks are covered. Continue only non-critical hardening slices as discovered. |
| Shared ZIP/OPC package | dependency for package readers | partial dependency | 107 | 67 | Selected-entry role bucket handoff is covered; finish shared ZIP/OPC package ingestion used by DOCX, EPUB, ODT, PPTX, and XLSX. |
| CSL/BibTeX/BibLaTeX/csljson citations | `bibtex`, `biblatex`, `csljson`, `endnotexml`, `ris` | mixed | 80 | 8 | Article-number number/label rendering, citation prefix/suffix affix review metadata, bounded RIS item parsing, and bounded EndNote XML name-group diagnostics are covered; broader EndNote XML reader parity, broader RIS coverage, and full reader-registry parity remain. |
| LaTeX/TeX/math | `latex` | partial | 21 | 14 | Labelled note anchor handoff is covered for LaTeX writer output; finish LaTeX reader and math conversion parity. |
| DocBook/list/table geometry | `docbook` | partial | 23 | 16 | DocBook bibliography/reference diagnostics, inline media alt diagnostics, list metadata/diagnostics, structural review, structural/media packets, media role/caption diagnostics, and section metadata diagnostics are covered; finish DocBook XML reader parity, body conversion, inline/block/reference/bibliography mapping, actual media/admonition conversion, generated AST parity, and broader fixture hydration. |
| RTF | `rtf` | partial | 4 | 3 | Finish RTF reader parity. |
| Typst | `typst` | unsupported | 49 | 17 | Implement Typst reader; package dependency conflict, source-class, and unsupported-reason policy provenance are covered, but current evidence is boundary/provenance only. |
| PPTX/XLSX | `pptx`, `xlsx` | unsupported | 0 | 2 | Implement native package readers after ZIP/OPC and XML package foundations. |
| Wiki/roff/text markup readers | `asciidoc`, `creole`, `djot`, `dokuwiki`, `fb2`, `haddock`, `jira`, `man`, `mdoc`, `mediawiki`, `muse`, `opml`, `org`, `pod`, `rst`, `t2t`, `textile`, `tikiwiki`, `twiki`, `vimwiki` | unsupported diagnostics | 0 | 20 | Diagnostic packets record 20/0 reader state plus rare text direction buckets, extension inference, AsciiDoc output aliases, unsupported summaries, parity counts, and wiki alias-collision taxonomy for the `wiki` suffix and `.wiki` MediaWiki/Vimwiki fixture-extension conflict; implement native readers or explicitly defer them. |
| Tabular/data readers | `csv`, `tsv` | partial | 12 | 2 | Headed and no-header table option handling, auto format inference diagnostics, registry option profiles, extension inference, direction buckets, source provenance, dialect profile review packets, conflict resolution, writer-reason payloads, and deterministic row-repair provenance are covered; finish broader CSV/TSV reader parity beyond the bounded table slices. |
| Unsupported input format surfaces | all unsupported input tokens above | unsupported | 0 | 28 | Close the remaining unsupported input registry rows. |

Adjacent import targets outside the Pandoc input denominator:

| Target | Current evidence | Scope note | Remaining input work |
| --- | ---: | --- | --- |
| PDF | 49 / 17 | Pandoc has `pdf` as an output target, not an input format. | Track as separate PDF import/markerPDF ingestion work. |
| Legacy DOC/CFB | 7 / 7 | Not a current upstream Pandoc input token. | Decide and track as separate legacy document import support. |
| IPYNB/notebook | skipped | Upstream Pandoc input token intentionally skipped for this phase. | No work in this burn-down. |

### ODF Manifest Encryption Method Summary Update (2026-06-15)

Bounded native PHP ODF/ODT package-ingestion coverage advanced by one rich-reader manifest encryption method summary case after the EPUB semantic note pairing slice. `OdfReader` now summarizes blocked package-part encryption methods across rich import reports and document manifest metadata, including record counts, checksum-type, algorithm, key-derivation, start-key-generation, issue-code, byte-exposure policy, and script-package provenance rows.

Verification passed `php -l` for `OdfReader.php` and `OdfReaderTest.php`; focused `OdfReaderTest.php` passed (`1` file, `4,910` assertions, `0` failures); focused ODF/ODT readiness passed (`5` files, `6,727` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `86,301` assertions, `0` failures). No Pandoc binary, office suite, zip/unzip, ZipArchive, browser renderer, online service, live provider test, live-service provider test, or external validator was invoked.

ODF/ODT evidence is now 82 local mapped cases against the 20 accepted static upstream ODF/ODT rows. The ship-ready verdict remains unchanged with 0 critical ODF/ODT gaps.

### ODF Manifest Generic Media Family Update (2026-06-15)

Bounded native PHP ODF/ODT package-ingestion coverage advanced by one manifest media-family fallback case after the BibLaTeX authority-list handoff slice. `OpenDocumentPackage` now classifies generic `application/octet-stream` manifest entries by package path when they identify image/audio media resources, while preserving non-media octet-stream entries as binary.

Verification passed `php -l` for `OpenDocumentPackage.php` and `OpenDocumentPackageTest.php`; focused `OpenDocumentPackageTest.php` passed (`1` file, `1,517` assertions, `0` failures); focused ODF/ODT readiness passed (`5` files, `6,701` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `86,201` assertions, `0` failures). No Pandoc binary, office suite, zip/unzip, ZipArchive, browser renderer, online service, live provider test, live-service provider test, or external validator was invoked.

ODF/ODT evidence is now 81 local mapped cases against the 20 accepted static upstream ODF/ODT rows. The ship-ready verdict remains unchanged with 0 critical ODF/ODT gaps.

### ODF Manifest Encryption Summary Update (2026-06-15)

Bounded native PHP ODF/ODT package-ingestion coverage advanced by two package-encryption summary cases after the EPUB supplemental OCF sidecar report slice. `OpenDocumentPackage` and `OdfReader` now aggregate manifest encryption review provenance across compact summaries, rich import reports, document manifest metadata, and package provenance, including encrypted parts, encryption record counts, checksum-type, algorithm, key-derivation, start-key-generation, unknown child element-name, and issue-code buckets while keeping encrypted package bytes blocked.

Verification passed `php -l` for `OpenDocumentPackage.php`, `OdfReader.php`, `OpenDocumentPackageTest.php`, and `OdfReaderTest.php`; focused `OpenDocumentPackageTest.php` + `OdfReaderTest.php` passed (`2` files, `6,393` assertions, `0` failures); focused ODF/ODT readiness passed (`5` files, `6,693` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `86,002` assertions, `0` failures). No Pandoc binary, office suite, zip/unzip, ZipArchive, browser renderer, online service, live provider test, live-service provider test, or external validator was invoked.

ODF/ODT evidence is now 80 local mapped cases against the 20 accepted static upstream ODF/ODT rows. The ship-ready verdict remains unchanged with 0 critical ODF/ODT gaps.

### EPUB Package Root Authoring Report Update (2026-06-15)

Bounded native PHP EPUB3 package-reader coverage advances by one OPF package root authoring case after the OPF package/manifest/spine authoring attribute slice. `EpubPackageReader` now exposes `packageReport` provenance for OPF root id/version, unique-identifier binding, `xml:lang`, `dir`, `xml:base`, prefix declarations and diagnostics, custom authoring attributes, base-resolution policy, package prefix reports, package/manifest/spine authoring reports, and package identity diagnostics.

Verification passed after rebasing the slice onto current main `559492548e`: `php -l` for `EpubPackageReader.php` and `EpubPackageReaderTest.php`; focused `EpubPackageReaderTest.php` (`1` file, `1,138` assertions, `0` failures); focused `OdfOdtShipReadinessStatusTest.php` (`1` file, `24` assertions, `0` failures); full `lanes/pandoc/tests` (`46` files, `85,915` assertions, `0` failures); JSON validation; conflict-marker scan; and `git diff --check`. No Pandoc binary, EPUBCheck, zip/unzip, ZipArchive, browser renderer, online service, live provider test, live-service provider test, or external validator is invoked.

EPUB evidence is now 82 local mapped cases against the 9 accepted static upstream EPUB rows. The native PHP EPUB input package reader remains partial pending broader structural/content parity.

### CSL Article-Number Number/Label Update (2026-06-15)

Bounded native PHP CSL/BibLaTeX rendering coverage advanced by one article-number number/label case after the EPUB reader identity report slice. `CitationCslProcessor` now exposes imported BibLaTeX `eid` / `articlenumber` values to CSL `article-number` labels, number forms, numeric text forms, `is-numeric` conditionals, and sort keys.

Verification passed `php -l` for `CslStyle.php`, `CitationCslProcessor.php`, and `CitationCslProcessorTest.php`; focused `CitationCslProcessorTest.php` passed (`1` file, `5,813` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `85,500` assertions, `0` failures). No Pandoc binary, citeproc, BibTeX, Biber, bibliography manager, browser renderer, online service, live provider test, live-service provider test, or external validator was invoked.

CSL/BibTeX evidence is now 80 local mapped cases against the 8 accepted static upstream CSL/BibTeX rows. Citation support remains mixed pending broader reader-registry parity and remaining EndNote/RIS coverage.

### EPUB Reader Identity Report Update (2026-06-15)

Bounded native PHP EPUB3 package-reader coverage advanced by one package identity report case after the empty MetaMap constructor provenance slice. `EpubPackageReader` now exposes `packageReport` and `identityReport` with selected unique identifier provenance, identifier detail rows, identifier summaries, duplicate unique-identifier diagnostics, and duplicate `dc:identifier` value diagnostics.

Verification passed `php -l` for `EpubPackageReader.php` and `EpubPackageReaderTest.php`; focused `EpubPackageReaderTest.php` passed (`1` file, `1,042` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `85,479` assertions, `0` failures). No Pandoc binary, EPUBCheck, zip/unzip, ZipArchive, browser renderer, online service, live provider test, live-service provider test, or external validator was invoked.

EPUB evidence is now 78 local mapped cases against the 9 accepted static upstream EPUB rows. The native PHP EPUB input package reader remains partial pending broader structural/content parity.

### JSON/Native Empty MetaMap Constructor Update (2026-06-15)

Bounded native PHP JSON/native AST constructor coverage advanced by one empty metadata envelope case after the EPUB resource-property provenance slice. `PandocJsonReader` now records top-level `MetaMap` constructor and native payload provenance for explicit empty metadata envelopes while `PandocJsonWriter` and `NativeWriter` keep canonical empty metadata output.

Verification passed `php -l` for `PandocJsonReader.php`, `NativeReader.php`, and `PandocJsonNativeAstTest.php`; focused `PandocJsonNativeAstTest.php` passed (`1` file, `4,143` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `85,429` assertions, `0` failures). No Pandoc binary, JSON filter, Cabal/Haskell runner, browser renderer, online service, live provider test, live-service provider test, or external validator was invoked.

JSON/native evidence is now 87 local cases against the 252 accepted static upstream JSON/native artifacts. JSON/native remains partial, not ship-ready; broader native/json fixture parity, unsupported constructors, table/citation/metadata/raw round-trip edges, and invalid JSON/native block encodings remain open.

### EPUB Resource Property ZIP Provenance Update (2026-06-15)

Bounded native PHP EPUB3 package coverage advanced by one manifest resource-property ZIP provenance case after the JSON/native single-wrapped constructor payload slice. `EpubPackage` now reports existence, external/encrypted state, byte-exposure policy, byte/compressed-byte lengths, compression method/support, and CRC32 for flagged manifest resources while unsupported-compression resources remain metadata-only.

Verification passed `php -l` for `EpubPackage.php` and `EpubPackageTest.php`; focused `EpubPackageTest.php` passed (`1` file, `2,580` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `85,417` assertions, `0` failures). No Pandoc binary, EPUBCheck, zip/unzip, ZipArchive, browser renderer, online service, live provider test, live-service provider test, or external validator was invoked.

EPUB evidence is now 77 local mapped cases against the 9 accepted static upstream EPUB rows. The native PHP EPUB input package reader remains partial pending broader structural/content parity.

### JSON/Native Single-Wrapped Constructor Payload Update (2026-06-15)

Bounded native PHP JSON/native AST constructor coverage advanced by ten block and inline constructor payload cases after the EPUB reader metadata report slice. `PandocJsonReader` and `NativeReader` now accept single-wrapped constructor lists for `Emph`, `Strong`, `Underline`, `Strikeout`, `Superscript`, `Subscript`, `SmallCaps`, `Span`, `Note`, and `BlockQuote`, while JSON/native writers preserve unchanged wrappers and regenerate edited inline payloads canonically.

Verification passed `php -l` for `PandocJsonReader.php`, `NativeReader.php`, and `PandocJsonNativeAstTest.php`; focused `PandocJsonNativeAstTest.php` passed (`1` file, `4,131` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `85,387` assertions, `0` failures). No Pandoc binary, JSON filter, Cabal/Haskell runner, browser renderer, online service, live provider test, live-service provider test, or external validator was invoked.

JSON/native evidence is now 86 local cases against the 252 accepted static upstream JSON/native artifacts. JSON/native remains partial, not ship-ready; broader native/json fixture parity, unsupported constructors, table/citation/metadata/raw round-trip edges, and invalid JSON/native block encodings remain open.

### EPUB Reader Metadata Report Update (2026-06-15)

Bounded native PHP EPUB3 package-reader coverage advanced by eight OPF metadata report cases after the JSON/native legacy table width slice. `EpubPackageReader` now exposes `metadataItems` plus `metadataReport` summaries for item kinds, IDs, language and direction tags, schemes, creator roles, file-as names, refinement properties, and local/external metadata links.

Verification passed `php -l` for `EpubPackageReader.php` and `EpubPackageReaderTest.php`; focused `EpubPackageReaderTest.php` passed (`1` file, `992` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `85,261` assertions, `0` failures). No Pandoc binary, EPUBCheck, zip/unzip, ZipArchive, browser renderer, online service, live provider test, live-service provider test, or external validator was invoked.

EPUB evidence is now 76 local mapped cases against the 9 accepted static upstream EPUB rows. The native PHP EPUB input package reader remains partial pending broader structural/content parity.

### JSON/Native Legacy Table Width Constructor Update (2026-06-15)

Bounded native PHP JSON/native AST constructor coverage advanced by five legacy table width constructor mappings after the styled inline constructor slice. `PandocJsonReader` and `NativeReader` now accept legacy five-field table column widths encoded as numeric default or explicit widths plus tagged `ColWidthDefault`, scalar `ColWidth`, and single-wrapped `ColWidth` helpers, preserving tagged width sidecars through JSON/native writer round trips.

Verification passed `php -l` for `PandocJsonReader.php`, `NativeReader.php`, and `PandocJsonNativeAstTest.php`; focused `PandocJsonNativeAstTest.php` passed (`1` file, `4,005` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `85,215` assertions, `0` failures). No Pandoc binary, JSON filter, Cabal/Haskell runner, browser renderer, online service, live provider test, live-service provider test, or external validator was invoked.

JSON/native evidence is now 76 local cases against the 252 accepted static upstream JSON/native artifacts. JSON/native remains partial, not ship-ready; broader native/json fixture parity, unsupported constructors, table/citation/metadata/raw round-trip edges, and invalid JSON/native block encodings remain open.

### JSON/Native Styled Inline Constructor Update (2026-06-15)

Bounded native PHP JSON/native AST constructor coverage advanced by seven styled inline constructor mappings after the wrapped integer table helper slice. `PandocJsonReader` and `NativeReader` now preserve `Emph`, `Strong`, `Underline`, `Strikeout`, `Superscript`, `Subscript`, and `SmallCaps` wrapper sidecars through JSON and native readers/writers while rebuilt paragraphs drop stale paragraph sidecars and retain inline constructor payloads.

Verification passed `php -l` for `PandocJsonNativeAstTest.php`; focused `PandocJsonNativeAstTest.php` passed (`1` file, `3,977` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `85,187` assertions, `0` failures). No Pandoc binary, JSON filter, Cabal/Haskell runner, browser renderer, online service, live provider test, live-service provider test, or external validator was invoked.

JSON/native evidence is now 71 local cases against the 252 accepted static upstream JSON/native artifacts. JSON/native remains partial, not ship-ready; broader native/json fixture parity, unsupported constructors, table/citation/metadata/raw round-trip edges, and invalid JSON/native block encodings remain open.

### JSON/Native Wrapped Integer Table Helpers Update (2026-06-15)

Bounded native PHP JSON/native AST constructor coverage advanced by one table helper case after the EPUB collection hierarchy slice. `PandocJsonNativeAstTest` now preserves single-wrapped `RowHeadColumns`, `RowSpan`, and `ColSpan` helper constructors plus cell alignment sidecars through rebuilt JSON and native table wrappers.

Verification passed `php -l` for `PandocJsonNativeAstTest.php`; focused `PandocJsonNativeAstTest.php` passed (`1` file, `3,903` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `85,113` assertions, `0` failures). No Pandoc binary, JSON filter, Cabal/Haskell runner, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

JSON/native evidence is now 64 local cases against the 252 accepted static upstream JSON/native artifacts. JSON/native remains partial, not ship-ready; broader native/json fixture parity, unsupported constructors, table/citation/metadata/raw round-trip edges, and invalid JSON/native block encodings remain open.

### EPUB Collection Hierarchy Update (2026-06-15)

Bounded native PHP EPUB3 package coverage advanced by one OPF collection hierarchy case after the JSON/native LineBreak summary slice. `EpubPackage` now emits `collectionHierarchy` plus WordPress import hierarchy items and diagnostics, including collection path, depth, role, link, and diagnostic rollups for nested OPF collections.

Verification passed `php -l` for `EpubPackage.php` and `EpubPackageTest.php`; focused `EpubPackageTest.php` passed (`1` file, `2,550` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `85,057` assertions, `0` failures). No Pandoc binary, EPUBCheck, zip/unzip, ZipArchive, browser renderer, online service, live provider test, live-service provider test, or external validator was invoked.

EPUB evidence is now 68 local mapped cases against the 9 accepted static upstream EPUB rows. The native PHP EPUB input package reader remains partial pending broader structural/content parity.

### JSON/Native LineBreak Summary Update (2026-06-15)

Bounded native PHP JSON/native AST constructor coverage advanced by one LineBreak summary case after the EPUB direct XHTML table slice. `PandocJsonReader` now preserves `LineBreak` as newline text in shared summaries while keeping `SoftBreak` as a space, aligning JSON-reader summaries with `NativeReader` and preserving `SoftBreak`/`LineBreak` constructor payloads through `PandocJsonWriter` and `NativeWriter`.

Verification passed `php -l` for `PandocJsonReader.php` and `PandocJsonNativeAstTest.php`; focused `PandocJsonNativeAstTest.php` passed (`1` file, `3,847` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `85,012` assertions, `0` failures). No Pandoc binary, JSON filter, Cabal/Haskell runner, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

JSON/native evidence is now 63 local cases against the 252 accepted static upstream JSON/native artifacts. JSON/native remains partial, not ship-ready; broader native/json fixture parity, unsupported constructors, table/citation/metadata/raw round-trip edges, and invalid JSON/native block encodings remain open.

### EPUB Direct XHTML Table Update (2026-06-15)

Bounded native PHP EPUB3 package-reader coverage advanced by one direct XHTML table ingestion case after the ODF/ODT package signature sidecar matrix slice. `EpubPackageReader` now converts package XHTML table elements into shared table AST nodes with captions, table head/body/foot sections, header and data cells, colspan/rowspan, scope, alignment, source HTML attributes, nested inline/list cell content, and `TableGeometry` review packets without invoking Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

Verification passed `php -l` for `EpubPackageReader.php` and `EpubPackageReaderTest.php`; focused `EpubPackageReaderTest.php` passed (`1` file, `946` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `84,990` assertions, `0` failures). No Pandoc binary, EPUBCheck, zip/unzip, ZipArchive, browser renderer, online service, live provider test, live-service provider test, or external validator was invoked.

EPUB evidence is now 67 local mapped cases against the 9 accepted static upstream EPUB rows. The native PHP EPUB input package reader remains partial pending broader structural/content parity.

### ODF Preferred View Mode Review Update (2026-06-15)

Bounded native PHP ODF/ODT package-reader coverage advanced by eight manifest preferred-view-mode review cases after the manifest media-family matrix and compact manifest collision-provenance slices. `OdfReader` and `OpenDocumentPackage` now summarize root applicability, defined OASIS modes, vendor namespaced tokens, invalid unqualified tokens, invalid token items, non-root diagnostics, mode counts, and rich/compact package provenance parity.

Verification passed `php -l` for `OdfReader.php`, `OpenDocumentPackage.php`, and `OdfReaderTest.php`; focused ODF/ODT gate passed (`5` files, `6,550` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `84,891` assertions, `0` failures). No Pandoc binary, office suite, zip/unzip, ZipArchive, browser renderer, online service, live provider test, live-service provider test, or external validator was invoked.

ODF/ODT evidence is now 73 local mapped cases against the 20 accepted static upstream ODF/ODT rows, with 0 remaining critical ODF/ODT gaps. The native PHP ODF/ODT input ship-ready verdict remains unchanged.

### ODF Compact Manifest Attribute Collision Update (2026-06-14)

Bounded native PHP ODF/ODT package-reader coverage advanced by one compact manifest collision-provenance slice after the compact package policy matrix slice. `OpenDocumentPackageTest.php` now covers repeated custom `manifest:file-entry` attribute names across entries, same-prefix namespace rebinding, custom namespace attributes that shadow `manifest:media-type` and `manifest:full-path`, decoded package-path conflict rejection, stable `manifestCustomAttributeItems` ordering, and parity with `OdfReader` package provenance.

Verification passed `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`; focused `OpenDocumentPackageTest.php` passed (`1` file, `1,409` assertions, `0` failures); focused ODF/ODT gate passed (`5` files, `6,478` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `83,957` assertions, `0` failures). No Pandoc binary, office suite, zip/unzip, ZipArchive, browser renderer, Node tooling, online service, live provider test, live-service provider test, or external validator was invoked.

ODF/ODT evidence is now 55 local mapped cases against the 20 accepted static upstream ODF/ODT rows, with 0 remaining critical ODF/ODT gaps. The native PHP ODF/ODT input ship-ready verdict remains unchanged.

### ODF Package Handoff Order Compression Update (2026-06-14)

Bounded native PHP ODF/ODT package-reader coverage advanced by one handoff-order compression slice after the wiki alias collision taxonomy slice. `OdfReader` now verifies manifest and local ZIP entry ordering across mimetype, `META-INF/manifest.xml`, core XML parts, declared media, missing package parts, unsupported-compression byte blocks, script sidecars, and RDF metadata-only sidecars while keeping script/RDF bytes out of document media handoff.

Verification passed `php -l lanes/pandoc/tests/OdfReaderTest.php`; focused `OdfReaderTest.php` passed (`1` file, `4,769` assertions, `0` failures); focused ODF/ODT gate passed (`5` files, `6,351` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `83,368` assertions, `0` failures). No Pandoc binary, office suite, zip/unzip, ZipArchive, browser renderer, online validator, live provider, or external validator was invoked.

ODF/ODT evidence is now 54 local mapped cases against the 20 accepted static upstream ODF/ODT rows, with 0 remaining critical ODF/ODT gaps. The native PHP ODF/ODT input ship-ready verdict remains unchanged.

### Wiki Alias Collision Taxonomy Update (2026-06-14)

Bounded native PHP format-registry coverage advanced by one wiki-family alias collision taxonomy slice on current main `1bf195ba7b`. `PandocFormatRegistry` now exposes `wikiAliasCollisionDiagnostics()` and `wikiAliasCollisionReviewPacket()` for the `wiki` token-suffix collision and the `.wiki` MediaWiki/Vimwiki fixture-extension collision while preserving `.wiki => mediawiki` extension inference.

Verification passed `php -l` for `PandocFormatRegistry.php` and `PandocFormatRegistryTest.php`; focused `PandocFormatRegistryTest.php` passed (`1` file, `2,484` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `83,323` assertions, `0` failures). `jq empty` and `git diff --check` also passed. No Pandoc binary, wiki converter, browser renderer, Node tooling, online service, live provider, or external validator was invoked.

Wiki alias-collision evidence is one mapped registry slice with 72 focused assertions. Native wiki reader/writer parity remains unsupported; the packet keeps stable unsupported reader/writer reason payloads, empty native implementation records, `externalToolFree=true`, `directReaderParitySupported=false`, and `directWriterParitySupported=false`.

### JSON/Native Single-Wrapped Metadata Constructor Update (2026-06-14)

Bounded native PHP JSON/native AST constructor coverage advanced by one metadata constructor compatibility slice after the RIS attachment source-file review slice. `PandocJsonReader` and `NativeReader` now accept single-wrapped `MetaString`, `MetaBool`, `MetaInlines`, `MetaBlocks`, `MetaList`, and `MetaMap` content while `PandocJsonWriter` and `NativeWriter` preserve compatible wrapped metadata constructor sidecars until metadata values are edited.

Verification passed `php -l` for `PandocJsonReader.php`, `NativeReader.php`, `PandocJsonWriter.php`, `NativeWriter.php`, and `PandocJsonNativeAstTest.php`; focused `PandocJsonNativeAstTest.php` passed (`1` file, `3,391` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `83,138` assertions, `0` failures). No Pandoc binary, JSON filters, Cabal/Haskell runner, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

JSON/native evidence is now 62 local cases against the 252 accepted static upstream JSON/native artifacts. JSON/native remains partial, not ship-ready; broader native/json fixture parity, unsupported constructors, table/citation/metadata/raw round-trip edges, and invalid JSON/native block encodings remain open.

### ODT Compact Raw Name Provenance Update (2026-06-14)

Bounded native PHP ODF/ODT package-reader coverage advanced by one compact package inventory provenance slice after the CSL/BibLaTeX series-title alias handoff. `OpenDocumentPackage` now reports raw ZIP entry name provenance in compact package inventory review packets, including raw name hex, name encoding, decoded-name mismatch flags, CP437 legacy-name counters, and aggregate raw-name provenance entries while preserving URI-decoded manifest package paths and SHA-256 media byte provenance.

Verification passed `php -l` for `OpenDocumentPackage.php` and `OpenDocumentPackageTest.php`; focused `OpenDocumentPackageTest.php` passed (`1` file, `1,241` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `81,593` assertions, `0` failures). No Pandoc binary, office suite, zip/unzip, ZipArchive, browser renderer, Node tooling, online validator, live provider, or external validator was invoked.

ODF/ODT evidence is now 53 local mapped cases against the 20 accepted static upstream ODF/ODT rows, with 0 remaining critical ODF/ODT gaps. The native PHP ODF/ODT input ship-ready verdict remains unchanged.

### DOCX Custom XML Properties Schema Update (2026-06-14)

Bounded native PHP DOCX/OpenXML package-reader coverage advanced by one custom XML properties schema diagnostics slice after XML/HTML option and optgroup node review. `DocxOpenXmlReader` now aggregates custom XML properties sidecar schema-ref provenance, including duplicate schema refs, missing store item IDs, missing and external properties sidecars, invalid properties roots, content-type diagnostics, and semantic package inventory roles for custom XML item and properties targets.

Verification passed `php -l` for `DocxOpenXmlReader.php` and `DocxOpenXmlReaderTest.php`; focused `DocxOpenXmlReaderTest.php` passed (`1` file, `2,736` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `81,489` assertions, `0` failures). No Pandoc binary, Word, LibreOffice, office suite, zip/unzip, ZipArchive, browser renderer, Node tooling, online service, live provider, or external validator was invoked.

DOCX/OpenXML evidence is now 95 local cases against the 35 accepted static upstream DOCX/OpenXML rows. DOCX/OpenXML remains partial, not ship-ready; broader direct WordprocessingML reader parity for fields, content controls, revision markup, list/table behavior, and package edge cases remains open.

### Closure-Wave Evidence Snapshot (2026-06-13)

Current-main counters are reconciled through the ODF/ODT package handoff-order compression slice rebased on `6f3ab17956`: 3,519 PHP passes, 0 failures, and 3,438 mapped upstream cases out of the accepted 2,276-row static upstream inventory. Rows below summarize the recently landed closure-wave evidence; they are factual evidence counters, not global ship-ready claims. This refresh was checked with `jq empty`, `git diff --check`, syntax checks for `OdfReaderTest.php`, focused `OdfReaderTest.php` (`1` file, `4,769` assertions, `0` failures), focused ODF/ODT gate (`5` files, `6,351` assertions, `0` failures), and full `lanes/pandoc/tests` (`46` files, `83,368` assertions, `0` failures).

| Surface | Evidence state | Upstream denominator | Local passing numerator | Ship verdict | Remaining critical gaps |
| --- | --- | ---: | ---: | --- | --- |
| CSV/TSV direct readers and registry options | Landed headed/no-header option parity, auto CSV/TSV format inference diagnostics, tabular registry extension inference, direction buckets, source provenance, option profiles, dialect profile review packets, explicit-format/extension conflict resolution, unsupported writer reasons, and deterministic row-repair provenance | 2 CSV command fixtures plus registry/options harness slices | 12 local CSV/TSV/registry cases | Partial after bounded reader/profile/inference/registry/repair slices | Broader CSV/TSV option behavior, malformed input diagnostics, multiline-cell behavior, and table-reader edge cases. |
| ODF/ODT package reader | Landed compact raw ZIP name provenance, compact manifest custom file-entry attributes, rich package sidecar byte-exposure ordering, and manifest/local ZIP handoff ordering across missing package parts plus unsupported-compression media while preserving ship-ready closure | 20 ODF/ODT rows | 54 ODF/ODT cases; latest focused ODF/ODT gate run 6,351 assertions | Ship-ready | 0 critical ODF/ODT gaps for native PHP ODT import. |
| DOCX/OpenXML package reader | Landed subdocument, note/comment relationship, and custom XML properties schema diagnostics | 35 DOCX/OpenXML rows | 95 DOCX/OpenXML cases; latest focused run 2,736 assertions | Partial | Broader WordprocessingML/package reader parity, fields, content controls, revisions, lists, tables, and package edge cases. |
| EPUB direct package reader | Landed manifest suffix diagnostics, skipped spine-entry reports, XHTML definition-list handoff, XHTML table-section review metadata, compact nav/NCX label provenance, NCX hierarchy diagnostics, and nav fragment target diagnostics | 9 EPUB package rows | 65 EPUB evidence cases; latest focused `EpubPackageReaderTest.php` run 407 assertions | Partial | Broader direct EPUB package reader structural/content parity and upstream runner parity. |
| Shared ZIP/OPC package | Landed selected-entry handoff role buckets | 67 ZIP/OPC dependency rows | 107 ZIP/OPC evidence cases; latest focused run 4,448 assertions | Partial dependency | ZIP64 expansion, encrypted payload decryption, non-deflate extraction, cryptographic signature validation, and remaining DOCX/EPUB/PPTX/XLSX package-reader parity. |
| JSON/native table, list, raw, block, metadata, citation, and figure handoff | Landed Figure child block payload preservation, nested metadata payload preservation, mixed metadata block-container stress coverage, citation prefix/suffix payload preservation, raw HTML alias preservation, table-caption, table-attribute, table-ColSpec, mixed table caption/cell flushing, note-label, Div Plain, mixed block-container, mixed Figure link/raw/code handoff, task-list sidecar, definition-list term-group, and nullary helper payload slices | 252 JSON/native artifacts | 61 JSON/native cases; latest focused `PandocJsonNativeAstTest.php` run 3,014 assertions | Partial | Broader native/json fixture parity, unsupported constructors, and table/citation/metadata round trips. |
| Markdown block/list/note/section boundaries | Landed YAML metadata alias summaries, generic raw HTML serialization, HTML list item attributes, math attributes, nested fenced Div, escaped fenced-Div attributes, fenced Div section-reference boundaries, mixed-content nested-list, definition-list term-group, table-cell note placement, and header section Div slices | 1,096 Markdown-family rows | 452 Markdown-family cases; latest focused YAML/Markdown run 6,753 assertions | Partial | Markdown/CommonMark/GFM extension, YAML metadata diagnostics, and variant parity. |
| HTML/XML/JATS DOM diagnostics | Landed image-map association diagnostics for resolved, missing, duplicate, invalid, and unreferenced map states plus area shape/coords geometry, invalid coordinate diagnostics, and default-area precedence; plaintext tail consumption and missing raw/RCDATA/inert raw-text boundary diagnostics; template/noscript raw-text boundary handling; nested table row-level and row-group foster-parenting; partial direct input registry routing for `xml`, `jats`, and `bits` through `XmlHtmlDom`; unsupported direct-reader parity reason serialization; standalone object association provenance; JATS/BITS relationshipDiagnostics for figure/table/reference xref targets, inline xref local back-reference and unsupported citation-target diagnostics, titleMetadata/subtitleMetadata, section title paths, body roots, section hierarchy, xref resolution, references, figures, figure label/caption/title metadata counts, missing-metadata diagnostics, figure caption issue rows, duplicate label buckets, source positions, alt text, figure xref link summaries, reference identifier provenance and duplicate/missing identifier diagnostics, table-wraps, table body/row/cell metadata, back-matter ref-lists, reference metadata, citation xref targets, resolved/missing citation ids, unreferenced buckets, BITS book-part body metadata, and DocBook bibliography/reference, inline media alt, structural/media, media role/caption, and section metadata diagnostics | 29 XML/HTML/JATS/DocBook rows | 298 XML/HTML/JATS/DocBook cases; latest focused DOM run 3,505 assertions | Partial after bounded registry and review diagnostics | Implement full HTML5 tree construction and XML/JATS/BITS body/back matter, tables, figures, references, citations, and AST parity. |
| DocBook structural, media, section, bibliography, inline media, and list metadata diagnostics | Landed review-only DocBook 4/5 structure packets, bibliography/reference packets for ids, entry metadata, citation/xref targets, duplicate/missing targets, unsupported bibliography children, mediaobject/inlinemediaobject alt/textobject evidence, imagedata target summaries, missing-alt diagnostics, linkend/id associations, structural block/admonition/figure/mediaobject/imagedata/linkend/unsupported-child diagnostics, root/section title provenance, bounded media role/caption summaries, repeated role-target diagnostics, media target manifest linkage, and bounded `itemizedlist`, `orderedlist`, nested list, `variablelist`, `xml:id`, and unsupported `listitem` child diagnostic handoff | 16 DocBook/table geometry rows | 23 local DocBook/list/table cases; latest focused `XmlHtmlDomTest.php` run 3,505 assertions | Partial after bounded media role/caption diagnostics and list metadata | Full DocBook body conversion, inline/block/reference/bibliography mapping, generated AST parity, broader fixture hydration, and actual figure/media/admonition conversion remain open. |
| IPYNB/notebook reader diagnostics | Landed metadata keys, cell tags, MIME summaries, blocked resource diagnostics, metadata-only attachment media extraction plans, nbformat major/minor plus cells-array schema diagnostics, deterministic output indexes, output display-order types, repeated MIME bundle keys, aggregate output diagnostics, and bounded source shape/line-ending diagnostics | 1 IPYNB rich-package reader bucket plus bounded nbformat/output/source diagnostics slices | Focused `IpynbReaderTest.php` run 506 assertions | Covered bounded reader, nbformat, output display-order, and source diagnostics | Full Jupyter notebook reader parity, rich output rendering, broader notebook schema parity, and native IPYNB writer support remain open. |
| Media linked-resource handoff | Landed linked-resource loading, MIME inference, and duplicate repair MIME summaries | 3 mapped cross-format resource slices | 3 focused `MediaBag` cases; latest focused run 198 assertions | Covered bounded handoff; not an input-format ship gate | Wider media/resource edge cases outside opt-in linked-resource handoff. |
| CSL citation/bibliography handoff | Landed citation prefix/suffix affix review metadata, RIS parsing, EndNote XML name-group diagnostics, direct CSL alias normalization, BibTeX/BibLaTeX metadata aliases, and bounded CSL rendering diagnostics | 8 CSL/BibTeX/BibLaTeX/csljson/RIS/EndNote XML rows | 79 CSL/BibTeX/BibLaTeX/csljson/RIS/EndNote XML cases; latest focused `CitationCslProcessorTest.php` run 5,385 assertions | Partial | Broader EndNote XML reader parity, broader RIS tag coverage, bibliography reader-registry parity, and wider CSL handoff diagnostics. |
| Notes/references | Landed LaTeX labelled note anchors, footnote label anchors, WordPress backlink metadata, table-cell note placement, and JSON/native note-label sidecars | 252 JSON/native artifacts plus notes/reference handoff slices | Latest focused `LatexWriterTest.php` run 22 assertions; focused Markdown/DOCX run 11,750 assertions | Partial | Broader note/reference placement, endnote grouping, complex anchor round-trips, and constructor parity. |
| Inline writer attributes | Landed WordPress semantic/math inline attrs and LaTeX semantic/code/math id anchors | Writer handoff slice | Focused Markdown/LaTeX writer run 6,665 assertions | Covered bounded writer handoff | Broader Markdown/CommonMark/GFM and LaTeX/math reader parity. |
| Wiki/roff/text reader ship gate | Landed explicit unsupported verdict and diagnostic packet for 20 text markup reader input tokens plus rare text input/output direction, extension, alias, unsupported, parity-count review packets, and wiki alias-collision taxonomy for `wiki` suffix and `.wiki` MediaWiki/Vimwiki conflicts | 20 wiki/roff/man/text markup tokens plus rare text registry surfaces | Focused registry run 2,484 assertions, including 72 wiki alias-collision assertions | Unsupported | Implement native readers after registry-level ship-gate accounting or explicitly defer them. |
| Table geometry | Landed LaTeX table-foot longtable, body-local head-row, and PlainWriter body-group handoffs | Table writer geometry slice | Focused PlainWriter/TableGeometry runs 2,127 assertions | Covered bounded table-foot, body-head-row, and plain-text body-group slices | Markdown/AsciiDoc/LaTeX body-group semantics, rowspan output, and package-specific table internals. |
| PDF/Typst dependency policy | Landed zero-format dependency sidecar parsing plus package dependency conflict, source-class, and unsupported-reason policy provenance | 17 PDF/Typst boundary rows | 51 PDF/Typst boundary/provenance cases; latest focused run 2,543 assertions | Covered bounded no-engine provenance | Real PDF/Typst output parity remains unsupported without external engines. |

### PDF/Typst Zero Dependency Sidecar Update (2026-06-14)

Bounded native PHP PDF/Typst handoff coverage advanced by one zero-format dependency sidecar case. `PdfEngineHandoff` now treats explicit Typst `--deps-format=zero` artifacts as NUL-delimited input-only dependency lists, preserving local inputs, Typst package review metadata, root read-boundary policy, and dependency-output review for the intentionally absent output target.

Verification passed `php -l` for `PdfEngineHandoff.php` and `PdfEngineHandoffTest.php`; focused `PdfEngineHandoffTest.php` passed (`1` file, `2,543` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `83,483` assertions, `0` failures). No Pandoc binary, Typst, TeX/PDF engine, browser renderer, online service, live provider test, or external validator was invoked.

PDF/Typst boundary/provenance evidence is now 51 local mapped cases against the 17 accepted PDF/Typst boundary rows. Real PDF/Typst rendering and output parity remain intentionally unsupported without external engines.

### CSV/TSV Row Repair Provenance Update (2026-06-14)

Bounded native PHP CSV/TSV reader coverage advanced by two DelimitedTextReader row-repair provenance cases after DocBook bibliography media crosslink diagnostics. The focused coverage records relaxed row padding, strict mismatch diagnostics, skipped blank row provenance, trailing empty field provenance, original versus repaired column counts, stable repair summaries, and CSV multiline/BOM-prefix behavior while preserving existing quote and escape handling.

Verification passed `php -l` for `DelimitedTextReaderTest.php`; focused `DelimitedTextReaderTest.php` passed (`1` file, `328` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `82018` assertions, `0` failures). No Pandoc binary, spreadsheet application, browser renderer, Node tooling, online service, live provider test, or external validator was invoked.

CSV/TSV/registry evidence is now 12 local mapped cases against the 2 accepted static upstream CSV command fixture rows. CSV/TSV remains partial; broader upstream fixture hydration, option behavior, malformed input diagnostics, multiline-cell behavior, and table-reader edge cases remain open.

### CSV/TSV Dialect Profile Registry Update (2026-06-13)

Bounded native PHP format-registry coverage advanced by one registry-only CSV/TSV dialect profile slice after XML namespace registry review packets. `PandocFormatRegistry` now generates CSV/TSV dialect profile review packets with stable `Text.Pandoc.Readers.CSV::readCSV` / `readTSV` source provenance buckets, explicit-format-vs-extension conflict probes where the explicit format wins, reader-only direction counts, unsupported writer reason payloads, and deterministic option-profile ordering while preserving `DelimitedTextReader` behavior and existing extension inference.

Verification covers `php -l` for `PandocFormatRegistry.php` and `PandocFormatRegistryTest.php`, focused `PandocFormatRegistryTest.php`, full `lanes/pandoc/tests`, `jq empty`, and `git diff --check`. No Pandoc binary, spreadsheet application, browser renderer, Node tooling, online service, live provider test, or external validator was invoked.

CSV/TSV/registry evidence is now 10 local mapped cases against the 2 accepted static upstream CSV command fixture rows. CSV/TSV remains partial; broader upstream fixture hydration, option behavior, malformed input diagnostics, multiline-cell behavior, and table-reader edge cases remain open.

### JSON/Native Mixed Metadata Container Update (2026-06-13)

Bounded native PHP JSON/native AST coverage advanced by one mixed metadata block-container stress slice after BibLaTeX translated subtitle aliases. `PandocJsonNativeAstTest.php` now covers nested `MetaInlines` and `MetaBlocks`, metadata `Span`/`Div` payloads, adjacent `Note`, `BlockQuote`, `Div`, and table-cell containers, and `Cite` fixture preservation across JSON/native writers and readers.

Verification passed `php -l` for `PandocJsonNativeAstTest.php`; `jq empty` for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`; focused `PandocJsonNativeAstTest.php` passed (`1` file, `3014` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `79751` assertions, `0` failures). No Pandoc binary, JSON filters, Cabal/Haskell runner, Node tooling, browser renderer, online service, live provider test, or external validator was invoked.

JSON/native evidence is now 61 local cases against the 252 accepted static upstream JSON/native artifacts. Broader native/json fixture parity, unsupported constructors, and table/citation/metadata round trips remain partial.

### JSON/Native Table ColSpec Sidecar Update (2026-06-13)

Bounded native PHP JSON/native AST coverage advanced by one table column-specification sidecar slice after IPYNB source line-ending diagnostics. `PandocJsonNativeAstTest.php` now covers `Align*` and `ColWidth*` payload sidecars through safe table rebuilds, verifies unchanged colspec payloads survive JSON and native writers, and verifies an edited numeric column width regenerates only that stale width payload while preserving unchanged alignment and neighboring width sidecars.

Verification passed `php -l` for `PandocJsonNativeAstTest.php`; `jq empty` for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`; focused `PandocJsonNativeAstTest.php` passed (`1` file, `2689` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `78117` assertions, `0` failures). No Pandoc binary, JSON filters, Cabal/Haskell runner, Node tooling, browser renderer, online service, live provider test, or external validator was invoked.

JSON/native evidence is now 60 local cases against the 252 accepted static upstream JSON/native artifacts. Broader native/json fixture parity, unsupported constructors, and table/citation/metadata round trips remain partial.

### IPYNB Source Line-Ending Diagnostics Update (2026-06-13)

Bounded native PHP IPYNB coverage advanced by one source diagnostics slice after DocBook inline media alt diagnostics. `IpynbReader` now reports string versus line-array source shape, source part count, byte count, logical line count, LF/CRLF/CR line-ending counts, selected line-ending style, trailing-newline state, and empty-source diagnostics at cell and document levels without duplicating source text into diagnostic metadata.

Verification passed `php -l` for `IpynbReader.php` and `IpynbReaderTest.php`; `jq empty` for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`; focused `IpynbReaderTest.php` passed (`1` file, `506` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `78061` assertions, `0` failures). No Jupyter, Python notebook execution, Pandoc binary, Cabal/Haskell runner, browser renderer, Node tooling, online service, live provider test, or external validator was invoked.

IPYNB evidence is now 9 local bounded reader/diagnostic cases, including source line-ending, output display-order, repeated MIME-bundle key, schema, nbformat, attachment, rich-output policy, and metadata/resource diagnostics. Full Jupyter notebook reader parity, rich output rendering, broader notebook schema parity, and native IPYNB writer support remain open.

### DocBook Inline Media Alt Diagnostics Update (2026-06-13)

Bounded native PHP DocBook coverage advanced by one inline media alt diagnostics slice after IPYNB output display-order diagnostics. `XmlHtmlDom` now reports mediaobject and inlinemediaobject alt/textobject fallback evidence, imagedata target path/basename/extension/content-type summaries, missing-alt diagnostics, and linkend/id association diagnostics while keeping `directReaderParity=false`.

Verification passed `php -l` for `XmlHtmlDom.php` and `XmlHtmlDomTest.php`; `jq empty` for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`; focused `XmlHtmlDomTest.php` passed (`1` file, `2959` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `78026` assertions, `0` failures). No Pandoc binary, XML validator, browser, Node tooling, online service, live provider test, or external validator was invoked.

DocBook/list/table geometry evidence is now 22 local cases against the 16 accepted static upstream DocBook/table rows. Full DocBook body conversion, inline/reference rendering, media conversion, generated AST parity, and broader fixture hydration remain partial.

### IPYNB Output Display-Order Diagnostics Update (2026-06-13)

Bounded native PHP IPYNB coverage advanced by one output display-order diagnostics slice after DocBook bibliography/reference diagnostics. `IpynbReader` now records deterministic output indexes, output display-order types, repeated MIME bundle keys across display and execute results, mixed-output aggregate diagnostics, and execution-count mismatch records while keeping notebook output bytes metadata-only.

Verification passed `php -l` for `IpynbReader.php` and `IpynbReaderTest.php`; `jq empty` for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`; focused `IpynbReaderTest.php` passed (`1` file, `471` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `77981` assertions, `0` failures). No Jupyter, Python notebook execution, Pandoc binary, Cabal/Haskell runner, browser renderer, Node tooling, online service, live provider test, or external validator was invoked.

IPYNB evidence is now 8 local bounded reader/diagnostic cases, including output display-order, repeated MIME-bundle key, schema, nbformat, attachment, rich-output policy, and metadata/resource diagnostics. Full Jupyter notebook reader parity, rich output rendering, broader notebook schema parity, and native IPYNB writer support remain open.

### DocBook Bibliography Reference Diagnostics Update (2026-06-13)

Bounded native PHP DocBook coverage advanced by one bibliography/reference diagnostics slice after EndNote XML name group diagnostics. `XmlHtmlDom::summarizeDocBookBibliography()` now reports bibliography and entry ids, titles, authors, year-like metadata, citation/xref/link targets, duplicate bibliography ids, missing reference targets, and unsupported bibliography child summaries.

Verification passed `php -l` for `XmlHtmlDom.php` and `XmlHtmlDomTest.php`; `jq empty` for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`; focused `XmlHtmlDomTest.php` passed (`1` file, `2914` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `77924` assertions, `0` failures). No Pandoc binary, XML validator, browser, Node tooling, online service, live provider test, or external validator was invoked.

DocBook/list/table geometry evidence is now 21 local cases against the 16 accepted static upstream DocBook/table rows. Full DocBook body conversion, inline/reference rendering, media conversion, generated AST parity, and broader fixture hydration remain partial.

### EndNote XML Name Group Diagnostics Update (2026-06-13)

Bounded native PHP CSL/EndNote XML coverage advanced by one name-group diagnostics slice after IPYNB nbformat diagnostics. `CitationCslProcessor::endnoteXmlItems()` now parses personal and corporate author groups, secondary-author/editor aliases, empty and malformed name-part diagnostics, stable raw unsupported-field preservation, source attachment diagnostics, and locator suffix diagnostics.

Verification passed `php -l` for `CitationCslProcessor.php` and `CitationCslProcessorTest.php`; `jq empty` for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`; focused `CitationCslProcessorTest.php` passed (`1` file, `5385` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `77866` assertions, `0` failures). No Pandoc binary, citeproc, BibTeX, Biber, Node tooling, online service, live provider test, or external validator was invoked.

CSL/BibTeX/BibLaTeX/csljson/RIS/EndNote XML evidence is now 79 local cases against the 8 accepted static upstream citation rows. Broader EndNote XML reader parity, broader RIS tag coverage, bibliography reader-registry parity, and wider CSL handoff diagnostics remain partial.

### IPYNB Nbformat Diagnostics Update (2026-06-13)

Bounded native PHP IPYNB coverage advanced by one nbformat version diagnostics slice after JATS/BITS figure caption metadata diagnostics. `IpynbReader` now reports missing, invalid, unsupported, and future nbformat major/minor diagnostics plus missing or invalid cells arrays as metadata-only schema review packets while preserving raw nbformat values and resource/output byte blocking.

Verification passed `php -l` for `IpynbReader.php`, `PandocFormatRegistry.php`, and `IpynbReaderTest.php`; `jq empty` for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`; focused `IpynbReaderTest.php` passed (`1` file, `414` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `77826` assertions, `0` failures). No Jupyter, Python notebook execution, Pandoc binary, Cabal/Haskell runner, browser renderer, Node tooling, online service, live provider test, or external validator was invoked.

IPYNB evidence is now 7 local bounded reader/diagnostic cases, including 1 nbformat diagnostics slice. Full Jupyter notebook reader parity, rich output rendering, broader notebook schema parity, and native IPYNB writer support remain open.

### JATS/BITS Figure Caption Metadata Update (2026-06-13)

Bounded native PHP XML/JATS/BITS coverage advanced by one figure caption metadata diagnostics slice after JSON/native citation prefix/suffix payload preservation. `XmlHtmlDom::summarizeJatsFrontMatter()` now emits figure metadata issue rows, duplicate label buckets, figure xref link summaries, source-position records, caption/title/alt-text metadata positions, and media target diagnostics while preserving `directReaderParity=false` and `figureMediaPayloadBytesExposed=false`.

Verification passed `php -l` for `XmlHtmlDom.php` and `XmlHtmlDomTest.php`; `jq empty` for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`; focused `XmlHtmlDomTest.php` passed (`1` file, `2856` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `77807` assertions, `0` failures). No Pandoc binary, Cabal/Haskell runner, browser renderer, Node tooling, external XML validator, online service, live provider test, or external validator was invoked.

XML/HTML/JATS/DocBook DOM evidence is now 295 local cases against the 29 accepted static upstream XML/HTML/JATS/DocBook rows. HTML5 tree construction remains partial; broader insertion-mode, malformed content, image-map semantics, and full XML/JATS/BITS direct reader parity remain open.

### DocBook Structural Media Diagnostics Update (2026-06-13)

Bounded native PHP DocBook coverage advanced by one structural/media diagnostics slice after CSV/TSV multiline quoted-record diagnostics. `XmlHtmlDom::summarizeDocBookReviewPacket()` now reports structural block inventories, admonition-like blocks, figures, mediaobject/imagedata references, xml:id/id targets, linkend resolution and missing-target summaries, and unsupported child diagnostics while keeping `directReaderParity=false`.

Verification passed `php -l` for `XmlHtmlDom.php` and `XmlHtmlDomTest.php`; `jq empty` for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`; focused `XmlHtmlDomTest.php` passed (`1` file, `2760` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `77591` assertions, `0` failures). No Pandoc binary, Cabal/Haskell runner, browser renderer, Node tooling, online validator, online service, live provider test, or external validator was invoked.

DocBook/list/table evidence is now 20 local cases against the 16 accepted static upstream DocBook/table rows. DocBook remains partial; full body conversion, inline/block/reference/bibliography mapping, generated AST parity, broader fixture hydration, and actual media/admonition conversion remain open.

### DocBook List Metadata Update (2026-06-13)

Bounded native PHP DocBook coverage advanced by one list metadata slice after JSON/native table body headRows payload preservation. `MarkdownReader` now parses safe DocBook `itemizedlist`, `orderedlist`, and `variablelist` XML into native list and definition-list AST nodes while preserving list titles, source attributes, ordered numeration/start metadata, `xml:id` listitem provenance, nested list blocks, and unsupported listitem child diagnostics.

Verification passed `php -l` for `MarkdownReader.php` and `MarkdownReaderTest.php`; focused `MarkdownReaderTest.php` passed (`1` file, `6766` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `76608` assertions, `0` failures). No Pandoc binary, Cabal/Haskell runner, browser renderer, Node tooling, online validator, online service, live provider test, or external validator was invoked.

DocBook/list/table evidence is now 19 local cases against the 16 accepted static upstream DocBook/table rows. DocBook remains partial; full body conversion, inline/block/reference/bibliography mapping, generated AST parity, broader fixture hydration, and figure/media/admonition conversion remain open.

### XML/HTML5 Raw Text Boundary Update (2026-06-13)

Bounded native PHP XML/HTML5 DOM coverage advanced by one raw-text boundary slice after XML/JATS/BITS figure metadata diagnostics. `XmlHtmlDom` now reports plaintext tail consumption plus missing raw/RCDATA/inert raw-text end tags with tag, kind, reason, closure mode, synthetic end tag, content byte count, and line/column. `Html5DomFragment` carries those diagnostics into `raw_html` AST attributes while preserving sanitized output.

Verification passed `php -l` for `XmlHtmlDom.php`, `Html5DomFragment.php`, `Html5DomFragmentTest.php`, and `XmlHtmlDomTest.php`; focused `Html5DomFragmentTest.php` plus `XmlHtmlDomTest.php` passed (`2` files, `4987` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `76484` assertions, `0` failures). No Pandoc binary, Cabal/Haskell runner, browser renderer, Node tooling, online validator, online service, live provider test, or external validator was invoked.

XML/HTML/JATS/DocBook DOM evidence is now 292 local cases against the 29 accepted static upstream XML/HTML/JATS/DocBook rows. HTML5 tree construction remains partial; broader insertion-mode, malformed content, image-map semantics, and full XML/JATS/BITS direct reader parity remain open.

### XML/JATS/BITS Figure Metadata Update (2026-06-13)

Bounded native PHP XML/JATS/BITS DOM coverage advanced by one figure metadata slice after XML/HTML5 image-map area geometry diagnostics. `XmlHtmlDom::summarizeJatsFrontMatter()` now emits per-figure labels, titles, caption text, caption paragraphs, aggregate label/caption/title counts, and explicit missing label/caption/title diagnostics while preserving `directReaderParity=false`.

Verification passed `php -l` for `XmlHtmlDom.php` and `XmlHtmlDomTest.php`; focused `XmlHtmlDomTest.php` passed (`1` file, `2369` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `76451` assertions, `0` failures). No Pandoc binary, Cabal/Haskell runner, browser renderer, Node tooling, external XML validator, online service, live provider test, or external validator was invoked.

At that point, XML/HTML/JATS/DocBook DOM evidence was 291 local cases against the 29 accepted static upstream XML/HTML/JATS/DocBook rows. HTML5 tree construction remained partial; broader insertion-mode, malformed content, image-map semantics, and full XML/JATS/BITS direct reader parity remained open.

### XML/HTML5 Image-map Area Geometry Update (2026-06-13)

Bounded native PHP XML/HTML5 DOM coverage advanced by one image-map area geometry slice after IPYNB schema diagnostics. `XmlHtmlDom` now summarizes area shape/coords geometry, invalid coordinate lists, default-area precedence, and usemap resolved/missing/duplicate/invalid/unreferenced association states while preserving existing image-map reference and hyperlink metadata.

Verification passed `php -l` for `XmlHtmlDom.php` and `XmlHtmlDomTest.php`; focused `XmlHtmlDomTest.php` passed (`1` file, `2328` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `76410` assertions, `0` failures). No Pandoc binary, browser renderer, Node tooling, online validator, online service, live provider test, or external validator was invoked.

At that point, XML/HTML/JATS/DocBook DOM evidence was 290 local cases against the 29 accepted static upstream XML/HTML/JATS/DocBook rows. HTML5 tree-construction remained partial; broader insertion-mode, malformed content, image-map semantics, and full XML/JATS/BITS direct reader parity remained open.

### EPUB NCX Hierarchy Diagnostics Update (2026-06-13)

Bounded EPUB package-reader coverage advanced by one NCX hierarchy diagnostics slice after JATS/BITS ref-list bibliography diagnostics. `EpubPackageReader` now reports duplicate NCX targets, missing `playOrder` counts, non-increasing positive `playOrder` diagnostics, flattened point counts, and max hierarchy depth while preserving nav/NCX label provenance and the legacy `epub.ncx` outline shape.

Verification passed `php -l` for `EpubPackageReader.php` and `EpubPackageReaderTest.php`; focused `EpubPackageReaderTest.php` passed (`1` file, `261` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `76366` assertions, `0` failures). No Pandoc binary, EPUBCheck, zip/unzip, ZipArchive, browser renderer, Node tooling, online service, live provider test, or external validator was invoked.

EPUB evidence is now 64 local cases against the 9 accepted static upstream EPUB package rows. Broader EPUB3 package reader structural/content parity and upstream runner parity remain partial.

### JATS/BITS Ref-list Bibliography Diagnostics Update (2026-06-13)

Bounded XML/JATS/BITS coverage advanced by one metadata-only ref-list bibliography slice. `XmlHtmlDom::summarizeJatsFrontMatter()` now reports ref-list/reference summaries, resolved and unresolved `bibr` xref inventories, unreferenced and missing-id reference counts, and unresolved bibliography diagnostics while preserving `directReaderParity=false` and avoiding raw citation text exposure in the public reference summaries.

Verification passed `php -l` for `XmlHtmlDom.php` and `XmlHtmlDomTest.php`; focused `XmlHtmlDomTest.php` passed (`1` file, `2310` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `76340` assertions, `0` failures). No Pandoc binary, Haskell/Cabal runner, browser renderer, Node tooling, external XML validator, online service, live provider test, or live-service provider test was invoked.

### JSON/native Figure Child Payload Update (2026-06-13)

Bounded native PHP JSON/native coverage advanced by one Figure child block payload slice after wiki input extension alias status. `PandocJsonWriter` now emits Figure child blocks through guarded block reuse after mixed inline flushing, so edited Figure captions regenerate wrapper constructors while unchanged child block native payloads survive JSON/native writer output.

Verification passed `php -l` for `PandocJsonWriter.php` and `PandocJsonNativeAstTest.php`; focused `PandocJsonNativeAstTest.php` passed (`1` file, `2293` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `76232` assertions, `0` failures). No Pandoc binary, Haskell/Cabal runner, JSON filters, browser renderer, Node tooling, online service, live provider test, or external validator was invoked.

JSON/native evidence is now 58 local cases against the 252 accepted static upstream JSON/native artifacts. Broader native/json fixture parity, unsupported constructors, and table/citation/metadata round trips remain partial.

### XML/HTML5 Template/Noscript Boundary Update (2026-06-13)

Bounded native PHP XML/HTML5 DOM coverage advanced by one template/noscript boundary slice after JATS/BITS inline xref diagnostics. `XmlHtmlDom` now keeps outer template content inert across nested template elements and noscript/script raw-text sentinel strings while preserving parsed review provenance for top-level template, noscript, script, and paragraph content.

Verification passed `php -l` for `XmlHtmlDom.php`, `Html5DomTest.php`, and `XmlHtmlDomTest.php`; focused `Html5DomTest.php` plus `XmlHtmlDomTest.php` passed (`2` files, `2448` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `76182` assertions, `0` failures). No Pandoc binary, Cabal/Haskell runner, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

XML/HTML/JATS/DocBook DOM evidence is now 288 local cases against the 29 accepted static upstream XML/HTML/JATS/DocBook rows. HTML5 tree-construction remains partial; broader insertion-mode, raw-text, malformed content, and full XML/JATS/BITS direct reader parity remain open.

### JATS/BITS Inline Xref Diagnostics Update (2026-06-13)

Bounded native PHP XML/JATS/BITS coverage advanced by one inline xref diagnostics slice after Markdown explicit fenced section boundaries. `XmlHtmlDom::summarizeJatsFrontMatter()` now reports back-matter reference IDs, body/book-body inline xref diagnostics, local back-reference target IDs, and unsupported missing or non-reference target metadata without invoking CSL rendering.

Verification passed `php -l` for `XmlHtmlDom.php` and `XmlHtmlDomTest.php`; focused `XmlHtmlDomTest.php` passed (`1` file, `2192` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `76152` assertions, `0` failures). No Pandoc binary, Cabal/Haskell runner, browser renderer, external XML validator, online service, live provider test, or live-service provider test was invoked.

XML/HTML/JATS/DocBook DOM evidence is now 287 local cases against the 29 accepted static upstream XML/HTML/JATS/DocBook rows. XML/JATS/BITS remains unsupported as full direct readers; full XML input reader mapping, JATS/BITS body/back matter/table/figure/reference/citation parity, HTML5 tree-construction parity, and DocBook XML reader parity remain open.

### JATS/BITS Relationship Diagnostics Update (2026-06-13)

Bounded native PHP XML/JATS/BITS coverage advanced by one relationship diagnostics slice after XML/HTML5 nested table foster-parenting. `XmlHtmlDom::summarizeJatsFrontMatter()` now carries a metadata-only `relationshipDiagnostics` packet for figure, table-wrap, and bibliographic reference xref targets, including per-target xref counts, resolved and unresolved xrefs, missing `rid` attributes, and `ref-type` target mismatches while keeping `directReaderParity=false`.

Verification passed `php -l` for `XmlHtmlDom.php` and `XmlHtmlDomTest.php`; focused `XmlHtmlDomTest.php` passed (`1` file, `2161` assertions, `0` failures); DOM family tests passed (`5` files, `5096` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `76047` assertions, `0` failures). No Pandoc binary, Cabal/Haskell runner, browser renderer, Node tooling, online sanitizer, external XML validator, online service, live provider test, or live-service provider test was invoked.

XML/HTML/JATS/DocBook DOM evidence is now 286 local cases against the 29 accepted static upstream XML/HTML/JATS/DocBook rows. XML/JATS/BITS remains unsupported as full direct readers; full XML input reader mapping, JATS/BITS body/back matter/table/figure/reference/citation parity, HTML5 tree-construction parity, and DocBook XML reader parity remain open.

### XML/HTML5 Table Foster-Parenting Update (2026-06-13)

Bounded native PHP XML/HTML5 DOM coverage advanced by one table foster-parenting slice after JATS/BITS title metadata propagation. `XmlHtmlDomTest.php` now verifies row-level `<em>` content inside `<tr>` and row-group-level `<span>` content inside `<tbody>` are foster-parented before the table, while valid `<caption>`, `<tbody>`, `<tr>`, and `<td>` structure, summary text, emphasis semantics, deterministic serialization, and WordPress raw HTML handoff remain intact.

Verification passed `php -l` for `XmlHtmlDomTest.php`; focused `XmlHtmlDomTest.php` passed (`1` file, `2133` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `76019` assertions, `0` failures). No Pandoc binary, Cabal/Haskell runner, browser renderer, Node tooling, online sanitizer, external validator, online service, live provider test, or live-service provider test was invoked.

XML/HTML/JATS/DocBook DOM evidence is now 285 local cases against the 29 accepted static upstream XML/HTML/JATS/DocBook rows. HTML5 tree-construction remains partial; broader insertion-mode, malformed table, form-in-table, misplaced content, and full XML/JATS/BITS direct reader parity remain open.

### JATS/BITS Title Metadata Update (2026-06-13)

Bounded native PHP XML/JATS/BITS coverage advanced by one title metadata propagation slice after rare text-format registry unsupported diagnostics. `XmlHtmlDom::summarizeJatsFrontMatter()` now emits `titleMetadata` and `subtitleMetadata` records for JATS article and BITS book titles, plus aggregate `sectionTitlePaths` / `sectionTitlePathText` fields and recursive section `titlePath` / `titlePathText` metadata while keeping `directReaderParity=false`.

Verification passed `php -l` for `XmlHtmlDom.php` and `XmlHtmlDomTest.php`; focused `XmlHtmlDomTest.php` passed (`1` file, `2113` assertions, `0` failures); DOM family tests passed (`5` files, `5048` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `75999` assertions, `0` failures). No Pandoc binary, Cabal/Haskell runner, browser renderer, external XML validator, online service, live provider test, or live-service provider test was invoked.

XML/HTML/JATS/DocBook DOM evidence is now 284 local cases against the 29 accepted static upstream XML/HTML/JATS/DocBook rows. XML/JATS/BITS remains unsupported as full direct readers; full XML input reader mapping, JATS/BITS body/back matter/table/figure/reference/citation parity, HTML5 tree-construction parity, and DocBook XML reader parity remain open.

### Rare Text Registry Unsupported Update (2026-06-13)

Bounded native PHP format-registry coverage advanced by one rare text diagnostics slice after EPUB nav/NCX label provenance. `PandocFormatRegistry` now records rare text input/output buckets, extension inference, AsciiDoc output aliases, parity summaries, and explicit unsupported diagnostics for `org`, `rst`, `textile`, `muse`, `asciidoc`, `djot`, `fb2`, `haddock`, `opml`, `pod`, `t2t`, and related output-only rare text tokens.

Verification passed `php -l` for `PandocFormatRegistry.php` and `PandocFormatRegistryTest.php`; focused `PandocFormatRegistryTest.php` passed (`1` file, `1595` assertions, `0` failures), including `151` rare-text assertions; full `lanes/pandoc/tests` passed (`46` files, `75989` assertions, `0` failures). No external Pandoc binary, format-specific CLI, browser renderer, Node tooling, online service, live provider test, or external validator was invoked.

Rare text registry evidence is now 1 local unsupported diagnostics case. Direct native readers and writers remain unsupported; first parser/writer implementation work should stay explicitly separate from these bounded diagnostics.

### EPUB Nav/NCX Label Provenance Update (2026-06-13)

Bounded native PHP compact EPUB package coverage advanced by one label provenance slice after HTML image-map association diagnostics. `EpubPackageReader` now attaches `labelProvenance` to XHTML nav entries and NCX `navPoint` entries, preserving source element, normalized label text, language, direction, raw attributes, `epub:type` tokens, and image-label contributors while keeping href/path/fragment resolution, child entries, page-list, landmarks, and compact package behavior unchanged.

Verification passed `php -l` for `EpubPackageReader.php` and `EpubPackageReaderTest.php`; focused `EpubPackageReaderTest.php` passed (`1` file, `235` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `75838` assertions, `0` failures). No Pandoc binary, EPUBCheck, zip/unzip, browser renderer, Node tooling, online service, live provider test, or external validator was invoked.

EPUB package evidence is now 63 local cases against the 9 accepted static upstream EPUB package rows. EPUB remains partial; broader direct package reader structural/content parity, NCX/nav edge cases, media overlays, and upstream runner parity remain open.
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

Remaining notebook gaps: full Jupyter notebook reader parity, rich output rendering, broader attachment/media extraction, broader notebook schema parity, and native IPYNB writer support remain partial or unsupported.

### XML/HTML Object Association Update (2026-06-13)

Bounded native PHP HTML object handoff advanced by one standalone object association slice after MediaBag linked-resource MIME inference. `XmlHtmlDom` now summarizes object `form` owner metadata, valid and invalid `usemap` image-map targets, and `typemustmatch` state, while both HTML serializers emit `typemustmatch` as a boolean attribute.

Verification passed `php -l` for `XmlHtmlDom.php`, `Html5DomFragment.php`, and `XmlHtmlDomTest.php`; focused `XmlHtmlDomTest.php` passed (`1` file, `1874` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `75327` assertions, `0` failures). No Pandoc, Cabal/Haskell runner, browser renderer, online sanitizer, external validator, online service, live provider test, or live-service provider test was invoked.

HTML/XML/JATS evidence is now 277 local cases against the 29 accepted static upstream HTML/XML/JATS/DocBook rows. HTML/XML/JATS remains partial; broader HTML5 tree construction and full XML/JATS/BITS direct readers remain open.

### MediaBag MIME Inference Update (2026-06-13)

Bounded native PHP MediaBag coverage advanced by one linked-resource MIME inference slice after YAML metadata alias review summaries. `MediaBag` now infers common package/resource MIME types from package-local paths beyond images, PDF, and plain text, covering CSS, JavaScript, JSON/XML/HTML, audio/video, fonts, EPUB, Markdown, CSV, and TSV. Data URI and hashed remote or URL-suffixed resources receive MIME-derived hash extensions for the same resource classes.

The focused fixture proves inferred CSS, audio, font, and JSON MIME provenance through extraction attributes, Markdown output, WordPress links, and JSON/native round-trip. Verification passed `php -l` for `MediaBag.php` and `MediaBagTest.php`; focused `MediaBagTest.php` passed (`1` file, `168` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `75296` assertions, `0` failures). No Pandoc binary, Cabal/Haskell runner, browser renderer, office suite, TeX/PDF engine, Node tooling, online service, live provider test, or external validator was invoked.

MediaBag linked-resource evidence is now 2 local mapped resource slices. The broader media/resource surface remains partial for duplicate resource provenance, package-local path normalization, external/missing diagnostics, and writer handoff consistency edges outside this bounded MIME inference slice.

### MediaBag Linked-Resource Repair Diagnostics Update (2026-06-14)

Bounded native PHP MediaBag coverage advanced by one linked-resource repair diagnostic slice after EPUB page-list navigation collision diagnostics. `MediaBag` now emits a compact `media-resource-link-duplicate-mime-summary` diagnostic for linked-resource repair conflicts while preserving MIME inference, percent-decoded safe path normalization, case-folded extraction collision repair, content-type/path disagreement diagnostics, and stable `data-pandoc-media-*` provenance fields.

The focused fixture proves duplicate PDF MIME candidate summaries, percent-decoded image provenance, linked PDF provenance, case-folded path repair provenance, and extension/content-type disagreement reporting. Verification passed `php -l` for `MediaBag.php` and `MediaBagTest.php`; focused `MediaBagTest.php` passed (`1` file, `198` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `80917` assertions, `0` failures); `jq empty` passed; and `git diff --check` passed. No Pandoc binary, browser renderer, Node tooling, online service, live provider test, or external validator was invoked.

MediaBag linked-resource evidence is now 3 local mapped resource slices. The broader media/resource surface remains partial for external/missing diagnostics and writer handoff consistency edges outside this bounded repair diagnostic slice.

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

### XML/JATS/BITS Direct Input Registry Update (2026-06-13)

Bounded native PHP registry and DOM coverage advanced by one XML/JATS/BITS direct input registry slice after JATS/BITS back-matter reference diagnostics. `PandocFormatRegistry` now exposes `xml`, `jats`, and `bits` as partial direct input routes through `XmlHtmlDom` with bounded diagnostic surfaces, and `XmlHtmlDom::summarizeJatsFrontMatter()` serializes unsupported direct-reader parity status and reason fields while preserving `directReaderParity=false`.

Verification passed `php -l` for `PandocFormatRegistry.php`, `XmlHtmlDom.php`, `PandocFormatRegistryTest.php`, and `XmlHtmlDomTest.php`; focused `PandocFormatRegistryTest.php` plus `XmlHtmlDomTest.php` passed (`2` files, `3522` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `75791` assertions, `0` failures). No Pandoc, Cabal/Haskell runner, browser renderer, Node tooling, external XML validator, online service, live provider test, or live-service provider test was invoked.

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

### LaTeX Labelled Note Anchor Update (2026-06-13)

Bounded native PHP notes/reference coverage advanced by one LaTeX writer anchor slice after JSON/native raw HTML alias preservation. `LatexWriter` now emits de-duplicated `fn-*` hypertargets for valid labelled source notes inside footnotes while generated inline notes remain unlabelled.

Verification passed `php -l` for `LatexWriter.php` and `LatexWriterTest.php`; focused `LatexWriterTest.php` passed (`1` file, `22` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `75549` assertions, `0` failures). No Pandoc binary, Cabal/Haskell runner, TeX engine, browser renderer, Node tooling, office suite, online service, live provider test, live-service provider test, or external validator was invoked.

Notes/reference evidence now includes Markdown labelled-note output, WordPress `fn`/`fnref` anchors and backlinks, table-cell note placement, JSON/native note-label sidecars, and LaTeX labelled-note anchor preservation. Endnote grouping, complex note placement, and broader Native/Markdown/HTML/LaTeX/WordPress anchor round-trip parity remain partial.

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

Verdict: not shippable yet. The native PHP lane now has 78 local passing CSL/BibTeX/BibLaTeX/csljson/RIS evidence cases against 8 upstream citation/bibliography format-related rows (975.0% evidence ratio; local cases are intentionally more granular than the upstream denominator).

| Format focus | Upstream denominator | Local passing evidence | Evidence percent | Remaining critical gap |
| --- | ---: | ---: | ---: | --- |
| CSL/BibTeX/BibLaTeX/csljson/RIS/EndNote XML | 8 | 78 | 975.0% | Bounded RIS parsing and citation affix review metadata now exist in native PHP, but EndNote XML is still unsupported and RIS needs broader tag coverage plus registry-level reader parity before this family can ship. |

Implemented highest-impact gap: `CitationCslProcessor::risItems()` and `fromRis()` now parse bounded RIS article and report records through normalized CSL items, default citation clusters, bibliography entries, and WordPress review blocks for `TY`, `ID`, `AU`, `TI`, `T2`, `PY`, `VL`, `IS`, `SP`, `EP`, `DO`, `UR`, `KW`, `PB`, `CY`, and `N1` fields. Verification passed `php -l` for `CitationCslProcessor.php` and `CitationCslProcessorTest.php`, focused `CitationCslProcessorTest.php` (`1` file, `5308` assertions, `0` failures), and full `lanes/pandoc/tests` (`44` files, `73964` assertions, `0` failures). No Pandoc binary, citeproc, BibTeX, Biber, bibliography manager, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

Follow-up CSL affix review metadata gap: `CitationCslProcessor` now exposes citation prefix and suffix review attributes on normalized citation nodes, grouped affix rows and summaries on citation groups, and CSL text variables for `citation-prefix`, `citation-suffix`, and `citation-affix-summary`. Custom CSL layouts that render those variables no longer receive duplicate automatic affixes. Verification passed `php -l` for `CitationCslProcessor.php` and `CitationCslProcessorTest.php`; focused `CitationCslProcessorTest.php` passed (`1` file, `5321` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `75562` assertions, `0` failures). No Pandoc binary, citeproc, BibTeX, Biber, bibliography manager, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

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

### JSON/Native Raw HTML Alias Update (2026-06-13)

Bounded native PHP JSON/native raw HTML handoff advanced by one alias-preservation slice after PDF/Typst package unsupported-reason reporting. `PandocJsonReader` and `NativeReader` now hydrate `RawBlock`/`RawInline` formats `html4`, `html5`, and `xhtml` as `raw_html`/`raw_html_inline` while preserving the original format label for JSON/native writer round trips.

Verification passed `php -l` for `PandocJsonReader.php`, `NativeReader.php`, and `PandocJsonNativeAstTest.php`; focused `PandocJsonNativeAstTest.php` passed (`1` file, `2189` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `75541` assertions, `0` failures). No Pandoc binary, Cabal/Haskell runner, browser renderer, Node tooling, XML validator, online service, live provider test, live-service provider test, or external validator was invoked.

JSON/native evidence is now 55 local cases against the 252 accepted static upstream JSON/native artifacts. JSON/native remains partial; raw block/inline adjacency, disabled raw fallback diagnostics, direct Markdown generic raw policy, and broader CommonMark/GFM raw extension parity remain open.

### PDF/Typst Boundary Ship-Readiness Update (2026-06-13)

Verdict: not shippable for real PDF/Typst output parity because native PHP does not execute external TeX/Typst/PDF engines. Graceful no-external-engine boundary diagnostics now have 49 local mapped PDF/Typst boundary/provenance cases against 17 upstream format-related cases (288.2%), with no known critical uncovered graceful-boundary rows.

| Format focus | Upstream denominator | Local passing evidence | Evidence percent | Remaining critical gap |
| --- | ---: | ---: | ---: | --- |
| PDF/Typst graceful boundary/provenance diagnostics | 17 | 49 | 288.2% | Full output parity still requires external engine execution, which remains unsupported in native PHP. |

Implemented highest-impact gap: `PdfEngineHandoff::fakeRun()` now extends Typst package dependency policy with sidecar package input counts, metadata-only byte exposure, non-executed network policy, package coordinates, namespace counts, and multi-version conflict diagnostics while preserving successful graceful behavior without external engines. Verification passed `php -l` for `PdfEngineHandoff.php` and `PdfEngineHandoffTest.php`, focused `PdfEngineHandoffTest.php` (`1` file, `2225` assertions, `0` failures), and full `lanes/pandoc/tests` (`45` files, `74226` assertions, `0` failures). No Pandoc, Typst, TeX/PDF engine, browser, Node, online service, live provider, or external validator was invoked.

Follow-up provenance gap: `PdfEngineHandoff::fakeRun()` now classifies Typst package dependencies by source bucket while preserving the existing package dependency policy fields. Structured dependency rows include `sourceClass`, policy packets expose deterministic `sourceClasses` and `sourceClassCounts`, and diagnostics report `typst-package-dependency-source` counts for `custom-namespace`, `preview-registry`, and `typst-registry` dependencies. Verification passed `php -l` for `PdfEngineHandoff.php` and `PdfEngineHandoffTest.php`; focused `PdfEngineHandoffTest.php` passed (`1` file, `2231` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `75045` assertions, `0` failures). No Pandoc, Typst, TeX/PDF engine, browser renderer, Node tooling, online service, live provider, or external validator was invoked.

Follow-up unsupported-reason gap: `PdfEngineHandoff::fakeRun()` now reports deterministic unsupported package reasons for preview registry, Typst registry, and custom namespace dependencies while preserving metadata-only/no-network package policy behavior. Policy packets expose per-package `unsupportedPackageReasons`, aggregate `unsupportedReasonCounts`, and `typst-package-unsupported-reason:*` diagnostics. Verification passed `php -l` for `PdfEngineHandoff.php` and `PdfEngineHandoffTest.php`; focused `PdfEngineHandoffTest.php` passed (`1` file, `2237` assertions, `0` failures); full `lanes/pandoc/tests` passed (`46` files, `75511` assertions, `0` failures). No Pandoc, Typst, TeX/PDF engine, browser renderer, Node tooling, online service, live provider, or external validator was invoked.

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

Verdict: not shippable yet. The native PHP lane now has 57 local passing JSON/native AST evidence cases against 252 upstream native expected artifacts (22.6% evidence ratio). Broader native/json fixture parity, unsupported constructor surfaces, and table/citation/metadata/raw round-trip edges remain open.

| Format focus | Upstream denominator | Local passing evidence | Evidence percent | Remaining critical gap |
| --- | ---: | ---: | ---: | --- |
| JSON/native AST constructors and round trips | 252 | 58 | 23.0% | Broader upstream native/json fixture parity plus unsupported constructor/table/citation/metadata/raw surfaces beyond bounded nested metadata payload preservation, mixed Figure handoff, raw HTML aliases, sidecar reuse, Markdown fixture writer handoff, note label sidecars, task-list sidecars, nullary helper payload validation, and nullary block payload validation. |

Implemented highest-impact gap: `PandocJsonWriter` and `NativeWriter` now attach `taskChecked` sidecars to generated list-item block payloads, while `PandocJsonReader` and `NativeReader` restore `taskChecked`/`taskList` metadata so Markdown, WordPress, and LaTeX handoff paths preserve unchecked, checked, and nested task items after JSON/native round trips. Verification passed `php -l` for `PandocJsonReader.php`, `NativeReader.php`, `PandocJsonWriter.php`, `NativeWriter.php`, `PandocJsonNativeAstTest.php`, and `MarkdownReaderTest.php`; focused `PandocJsonNativeAstTest.php` (`1` file, `2015` assertions, `0` failures); focused `MarkdownReaderTest.php` (`1` file, `6626` assertions, `0` failures); and full `lanes/pandoc/tests` (`45` files, `74254` assertions, `0` failures). No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

Implemented follow-up gap: `PandocJsonWriter` and `NativeWriter` now reject stale non-empty nullary helper constructor payload reuse for quote, math, citation-mode, list-style/delimiter, table-alignment, and `ColWidthDefault` helpers. Regenerated JSON/native output drops stale `c` payloads while preserving valid helper sidecars and empty-`c` compatibility. Verification passed `php -l` for `PandocJsonWriter.php`, `NativeWriter.php`, and `PandocJsonNativeAstTest.php`; focused `PandocJsonNativeAstTest.php` (`1` file, `2097` assertions, `0` failures); and full `lanes/pandoc/tests` (`45` files, `74336` assertions, `0` failures). No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

Implemented follow-up gap: `PandocJsonWriter` and `NativeWriter` now reject stale non-empty `HorizontalRule` and `Null` block constructor payload reuse recursively inside `MetaBlocks`, `MetaList` payloads, list items, `Div`/`BlockQuote` containers, and `Note` block payloads. Readers still retain the source native sidecars for provenance, while regenerated JSON/native writer output emits canonical sidecar-free nullary block constructors. Verification passed `php -l` for `PandocJsonWriter.php`, `NativeWriter.php`, and `PandocJsonNativeAstTest.php`; focused `PandocJsonNativeAstTest.php` (`1` file, `2875` assertions, `0` failures); and full `lanes/pandoc/tests` (`46` files, `79494` assertions, `0` failures). No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderer, Node tooling, online service, live provider test, or external validator was invoked.

Implemented follow-up raw alias gap: `PandocJsonReader` and `NativeReader` now map `html4`, `html5`, and `xhtml` raw block/inline constructors into the same typed raw HTML nodes as `html` while preserving the source format for JSON/native writer round trips. Verification passed `php -l` for `PandocJsonReader.php`, `NativeReader.php`, and `PandocJsonNativeAstTest.php`; focused `PandocJsonNativeAstTest.php` (`1` file, `2189` assertions, `0` failures); and full `lanes/pandoc/tests` (`46` files, `75541` assertions, `0` failures). No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

Implemented mixed-Figure follow-up gap: `WordPressBlockWriter` now keeps simple single-image figures on `wp:image` while complex figures render as `wp:html` from the full figure child block sequence, preserving link and raw inline payloads around nested code blocks plus attributes and figcaption metadata. JSON/native writer output and reader round trips now have executable coverage for flushing mixed figure inline runs to `Plain` blocks around nested blocks. Verification passed `php -l` for `WordPressBlockWriter.php` and `PandocJsonNativeAstTest.php`; focused `PandocJsonNativeAstTest.php` (`1` file, `2227` assertions, `0` failures); and full `lanes/pandoc/tests` (`46` files, `75600` assertions, `0` failures). No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderer, Node tooling, online service, live provider test, or external validator was invoked.

Implemented nested metadata payload follow-up gap: `PandocJsonWriter` and `NativeWriter` now preserve unchanged nested `MetaString` and `MetaBool` sidecars from reader-indexed `metaConstructorProvenance` paths while rebuilding edited `MetaMap` and `MetaList` containers, including escaped metadata keys such as `owner/team`; edited values regenerate canonical constructors and stale edited-container sidecars are dropped. Verification passed `php -l` for `PandocJsonWriter.php`, `NativeWriter.php`, and `PandocJsonNativeAstTest.php`; focused `PandocJsonNativeAstTest.php` (`1` file, `2267` assertions, `0` failures); and full `lanes/pandoc/tests` (`46` files, `75640` assertions, `0` failures). No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderer, Node tooling, online service, live provider test, or external validator was invoked.

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
commit history agree on the current shipping call after the CSL citation affix
review metadata slice, LaTeX labelled note anchor preservation slice, JSON/native raw HTML alias preservation slice, PDF/Typst package unsupported-reason slice, JATS/BITS body
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
policy slice, PDF/Typst package unsupported-reason slice, Markdown/WordPress raw HTML boundary slice, Markdown generic raw
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
slice, XML/JATS/BITS direct reader capability diagnostics, JATS/BITS back-matter
reference diagnostics, JSON/native
block-container mixed-content flushing, Markdown math attribute round-trip,
Markdown HTML list item attribute handoff, PlainWriter table body-group
boundaries, Pandoc inline attribute writer handoff slice, and JSON/native mixed
Figure handoff slice, JSON/native nested metadata payload preservation slice,
IPYNB attachment media diagnostics slice, DocBook section metadata diagnostics
slice, JATS/BITS table body diagnostics slice, JATS/BITS back-matter
reference diagnostics slice, XML/JATS/BITS direct input registry slice,
HTML image-map association diagnostics slice, EPUB nav/NCX label provenance
slice, rare text-format registry unsupported diagnostics slice, JATS/BITS
title metadata propagation slice, XML/HTML5 nested table foster-parenting
slice, JATS/BITS relationship diagnostics slice, JATS/BITS inline xref
diagnostics slice, XML/HTML5 DOM template/noscript boundary slice, and
JSON/native Figure child block payload slice.

| Check | Evidence | Verdict |
| --- | --- | --- |
| Upstream denominator | Static upstream inventory remains 2,276 Pandoc test/data/benchmark artifacts at `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`; input-format scope is 50 tokens after skipping IPYNB for this phase. | Denominator accepted for native PHP progress accounting; not upstream runner parity. |
| Local passing numerator | `lane-status.json` reports 3,373 PHP passes / 0 failures, and `UPSTREAM_TEST_MANIFEST.json` reports 3,333 mapped upstream cases. | PHP lane remains green. |
| Percent | 3,333 / 2,276 = 146.4%; percentages above 100% reflect local PHP slices being more granular than upstream inventory rows. | High coverage, but not global ship-ready. |
| Shippable format gate | ODF/ODT is ship-ready with 53 local mapped cases / 20 upstream ODF/ODT cases, 265.0%, and 0 critical ODF/ODT gaps. | ODF/ODT can ship under the native PHP/no-external-validator policy. |
| Remaining critical gaps | 21 input tokens remain partial and 28 remain unsupported across DOCX/OpenXML, EPUB3, shared ZIP/OPC dependencies, JSON/native AST, CSV/TSV, CSL/BibTeX/BibLaTeX/csljson, HTML/XML/JATS DOM, LaTeX/TeX/math, Typst, PPTX/XLSX, and wiki/roff/text readers. JSON/native Figure child block payload preservation, XML/HTML5 DOM template/noscript boundary handling, JATS/BITS inline xref diagnostics, JATS/BITS relationship diagnostics, XML/HTML5 nested table foster-parenting, JATS/BITS title metadata propagation, rare text-format registry unsupported diagnostics, EPUB nav/NCX label provenance, HTML image-map association diagnostics, XML/JATS/BITS direct input registry routing, JATS/BITS back-matter reference diagnostics, JATS/BITS table body diagnostics, DocBook section metadata diagnostics, IPYNB attachment media diagnostics, JSON/native nested metadata payload preservation, JSON/native mixed Figure handoff, CSL citation affix review metadata, LaTeX labelled note anchor preservation, JSON/native raw HTML alias preservation, PDF/Typst package unsupported-reason reporting, JATS/BITS body diagnostics, CSV/TSV format inference diagnostics, DocBook structural review diagnostics, IPYNB notebook metadata/resource diagnostics, XML/HTML object association provenance, MediaBag linked-resource MIME inference, YAML metadata alias review summaries, JSON/native table attribute writer handoff, JSON/native mixed table caption/cell flushing, tabular data registry option profiles, ODF compact manifest custom attributes, DOCX note/comment relationship diagnostics, Markdown generic raw HTML serialization, Typst package source-class policy provenance, CSV/TSV header option parity, text markup unsupported diagnostics, shared ZIP selected-entry role buckets, EPUB XHTML table semantics, PlainWriter table body-group boundaries, Markdown HTML list item attribute handoff, Markdown math attribute round-trip, JSON/native block-container mixed-content flushing, XML/JATS/BITS direct reader capability diagnostics, Markdown header section Div mapping, Markdown table-cell note placement, DOCX/OpenXML subdocument diagnostics, definition-list term-group handoff, Markdown fenced Div section-reference boundaries, inline writer attributes, EPUB direct manifest/spine diagnostics, EPUB XHTML definition-list handoff, and text markup unsupported ship-gate accounting are covered. | Full Pandoc input lane remains active. |
| Stale assigned-open cleanup | `bd orphans --label lane:pandoc` was filtered to commits that are ancestors of `origin/main`; only `plib-qka5o` qualified and was closed as landed. Follow-up main-ancestor orphan count is 0. Branch-only orphan candidates were left open. | Dashboard queue state now reflects landed work without closing live branch work. |
| Verification | `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `git diff --check`, syntax checks for `PandocJsonWriter.php` and `PandocJsonNativeAstTest.php`, focused `PandocJsonNativeAstTest.php`, and `php tools/run-tests.php lanes/pandoc/tests` passed. | Focused: 1 file, 2,293 assertions, 0 failures. Full: 46 files, 76,232 assertions, 0 failures. |

Methodology: upstream denominators come from `lanes/pandoc/notes/upstream-inventory.md`,
`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, and the input-format registry in
`lanes/pandoc/src/PandocFormatRegistry.php`, which records 51 upstream Pandoc
input tokens from the 2026-06-03 manual and upstream source commit
`jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`. Local passing counters
merge `mapped*Cases` from `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` and current
`lanes/pandoc/lane-status.json`; `phpPass`/`phpFail` come from
`lanes/pandoc/lane-status.json`. Commands used: `jq` over the manifest and lane
status JSON to list case counters, PHP registry inspection for input support
status, `git diff --check -- progress.md PANDOC_STATUS.md lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/src/PandocJsonWriter.php lanes/pandoc/tests/PandocJsonNativeAstTest.php lanes/pandoc/notes/pandoc-json-native-figure-child-block-payloads-20260613.md`,
`php -l lanes/pandoc/src/PandocJsonWriter.php`,
`php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`,
`php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
(`1` file, `2293` assertions, `0` failures), and `php tools/run-tests.php lanes/pandoc/tests`
(`46` files, `76232` assertions, `0` failures after final rebase onto current main `1b07a9dc6e`).
`bd orphans --label lane:pandoc` was used for stale-open cleanup, but only
main-ancestor referenced commits were closed. No external Pandoc binary,
format-specific CLI, browser engine, Node tooling, online service, live provider
test, or external validator was invoked.
