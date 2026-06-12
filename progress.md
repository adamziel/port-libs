| Project | Focus | State | Progress | PHP Tests | Mapped Upstream | Unmapped | Next Gate | Commit |
| --- | --- | --- | ---: | ---: | --- | ---: | --- | --- |
| [libsqlite](lanes/libsqlite/lane-status.json) | Primary | PHP green, upstream gap | 99.6% | 6,290,284 pass / 0 fail | [1,589 / 1,589 (100.0%)](lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json) | 0 | No local blocker | 16d8081 |
| [LightningCSS](lanes/lightningcss/lane-status.json) | Active | PHP green, upstream gap | 99.8% | 9,280 pass / 0 fail | [2,445 / 3,532 (69.2%)](lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json) | 1,087 | Full upstream runner closure is partial: bounded Rust media test and... | pending isolate... |
| [gitoxide](lanes/gitoxide/lane-status.json) | Active | High coverage | 98.8% | 11,183 pass / 0 fail | [1,821 / 2,886 (63.1%)](lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json) | 1,065 | Cargo workspace blocked by sparse target files | 29e9ab4 |
| [markerPDF](lanes/markerpdf/lane-status.json) | Active | PHP green, upstream gap | 100.0% | 3,621 pass / 0 fail | [763 / 78 (978.2%)](lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json) | 0 | No GPU/model execution will be run for markerPDF under current user d... | pending fast ba... |
| [Readability/content rewrite engine](lanes/readability/lane-status.json) | Backlog | Active port | 85.0% | 154 pass / 0 fail | [1,578 / 1,984 (79.5%)](lanes/readability/UPSTREAM_TEST_MANIFEST.json) | 406 | No local blocker | cd2e8a0 |
| [pandoc](lanes/pandoc/lane-status.json) | Backlog | High coverage, active closures | 96.0% | 3,291 pass / 0 fail | [3,251 / 2,276 (142.8%)](lanes/pandoc/UPSTREAM_TEST_MANIFEST.json) | 0 | No landed stale-open blocker; continue format closures | progress-status-reconciliation-f4d0e410e4 |
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
| Partial native PHP input support to finish | 16 |
| Unsupported native PHP input tokens to implement | 33 |

Focused test counts below are evidence counters, not a strict remaining-test burn-down. Percentages above 100% mean the local PHP tests are more granular than the upstream case counter available for that family; they do not claim upstream runner parity.

| Input family | In-scope input tokens | Current input status | Local passing | Upstream denominator | Remaining input work |
| --- | --- | --- | ---: | ---: | --- |
| Markdown/CommonMark/GFM | `commonmark`, `commonmark_x`, `gfm`, `markdown`, `markdown_github`, `markdown_mmd`, `markdown_phpextra`, `markdown_strict` | partial | 439 | 1,096 | Complete extension and variant parity. |
| HTML/XML/JATS DOM | `html` partial; `xml`, `jats`, `bits` unsupported | mixed | 274 | 29 | Finish HTML5 tree construction and implement XML/JATS readers. |
| JSON/native AST | `json`, `native` | partial | 44 | 252 | Complete JSON/native AST constructor coverage. |
| DOCX/OpenXML | `docx` | partial | 92 | 35 | Finish remaining direct WordprocessingML/package reader parity; section-property review metadata is covered. |
| EPUB/EPUB3 | `epub` | partial | 58 | 9 | Finish EPUB package reader parity. |
| ODF/ODT/OpenDocument | `odt` | ship-ready | 49 | 20 | 0 critical gaps for native PHP ODT import; continue only non-critical hardening slices as discovered. |
| Shared ZIP/OPC package | dependency for package readers | partial dependency | 106 | 67 | Finish shared ZIP/OPC package ingestion used by DOCX, EPUB, ODT, PPTX, and XLSX. |
| CSL/BibTeX/BibLaTeX/csljson citations | `bibtex`, `biblatex`, `csljson`, `endnotexml`, `ris` | unsupported | 76 | 8 | Implement native bibliography and citation readers. |
| LaTeX/TeX/math | `latex` | partial | 20 | 14 | Finish LaTeX reader and math conversion parity. |
| DocBook/table geometry | `docbook` | partial | 16 | 16 | Finish DocBook XML reader parity. |
| RTF | `rtf` | partial | 4 | 3 | Finish RTF reader parity. |
| Typst | `typst` | unsupported | 45 | 17 | Implement Typst reader; current evidence is boundary/provenance only. |
| PPTX/XLSX | `pptx`, `xlsx` | unsupported | 0 | 2 | Implement native package readers after ZIP/OPC and XML package foundations. |
| Wiki/roff/text markup readers | `asciidoc`, `creole`, `djot`, `dokuwiki`, `fb2`, `haddock`, `jira`, `man`, `mdoc`, `mediawiki`, `muse`, `opml`, `org`, `pod`, `rst`, `t2t`, `textile`, `tikiwiki`, `twiki`, `vimwiki` | unsupported | 0 | 20 | Implement native text-format readers or explicitly defer them. |
| Tabular/data readers | `csv`, `tsv` | unsupported | 0 | 2 | Implement CSV/TSV table readers. |
| Unsupported input format surfaces | all unsupported input tokens above | unsupported | 0 | 33 | Close the remaining unsupported input registry rows. |

Adjacent import targets outside the Pandoc input denominator:

| Target | Current evidence | Scope note | Remaining input work |
| --- | ---: | --- | --- |
| PDF | 45 / 17 | Pandoc has `pdf` as an output target, not an input format. | Track as separate PDF import/markerPDF ingestion work. |
| Legacy DOC/CFB | 7 / 7 | Not a current upstream Pandoc input token. | Decide and track as separate legacy document import support. |
| IPYNB/notebook | skipped | Upstream Pandoc input token intentionally skipped for this phase. | No work in this burn-down. |

### ODF/ODT Ship Readiness

Format-specific closure on 2026-06-12: native PHP ODF/ODT input is shippable
for OpenDocument text packages under the current no-external-validator policy.

| Check | Evidence |
| --- | --- |
| Upstream format-related denominator | 20 ODF/ODT mapped upstream cases, 575 assertion targets in `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`. |
| Local passing numerator | 49 current mapped ODF/ODT cases and 1,458 focused assertions across `lanes/pandoc/lane-status.json` plus manifest-carried ODF/ODT counters. |
| Coverage percent | 245.0% by mapped case slices, 253.6% by focused assertions. Percentages above 100% reflect local PHP slices being more granular than the upstream inventory rows. |
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

### Pandoc Progress/Status Reconciliation

Dashboard reconciliation on 2026-06-12: `PANDOC_STATUS.md` is now present, the
root dashboard, lane status, upstream manifest, ready/open beads, and landed
commit history agree on the current shipping call after the DOCX section-property
slice.

| Check | Evidence | Verdict |
| --- | --- | --- |
| Upstream denominator | Static upstream inventory remains 2,276 Pandoc test/data/benchmark artifacts at `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`; input-format scope is 50 tokens after skipping IPYNB for this phase. | Denominator accepted for native PHP progress accounting; not upstream runner parity. |
| Local passing numerator | `lane-status.json` reports 3,291 PHP passes / 0 failures, and `UPSTREAM_TEST_MANIFEST.json` reports 3,251 mapped upstream cases. | PHP lane remains green. |
| Percent | 3,251 / 2,276 = 142.8%; percentages above 100% reflect local PHP slices being more granular than upstream inventory rows. | High coverage, but not global ship-ready. |
| Shippable format gate | ODF/ODT is ship-ready with 49 local mapped cases / 20 upstream ODF/ODT cases, 245.0%, and 0 critical ODF/ODT gaps. | ODF/ODT can ship under the native PHP/no-external-validator policy. |
| Remaining critical gaps | 16 input tokens remain partial and 33 remain unsupported across DOCX/OpenXML, EPUB3, shared ZIP/OPC dependencies, JSON/native AST, CSL/BibTeX/BibLaTeX/csljson, HTML/XML/JATS DOM, LaTeX/TeX/math, Typst, PPTX/XLSX, wiki/roff/text readers, and CSV/TSV. | Full Pandoc input lane remains active. |
| Stale assigned-open cleanup | `bd orphans --label lane:pandoc` was filtered to commits that are ancestors of `origin/main`; only `plib-qka5o` qualified and was closed as landed. Follow-up main-ancestor orphan count is 0. Branch-only orphan candidates were left open. | Dashboard queue state now reflects landed work without closing live branch work. |
| Verification | `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `git diff --check -- progress.md PANDOC_STATUS.md lanes/pandoc/lane-status.json`, and `php tools/run-tests.php lanes/pandoc/tests` passed. | 44 test files, 73,857 assertions, 0 failures. |

Methodology: upstream denominators come from `lanes/pandoc/notes/upstream-inventory.md`,
`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, and the input-format registry in
`lanes/pandoc/src/PandocFormatRegistry.php`, which records 51 upstream Pandoc
input tokens from the 2026-06-03 manual and upstream source commit
`jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`. Local passing counters
merge `mapped*Cases` from `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` and current
`lanes/pandoc/lane-status.json`; `phpPass`/`phpFail` come from
`lanes/pandoc/lane-status.json`. Commands used: `jq` over the manifest and lane
status JSON to list case counters, PHP registry inspection for input support
status, `git diff --check -- progress.md PANDOC_STATUS.md lanes/pandoc/lane-status.json`,
and `php tools/run-tests.php
lanes/pandoc/tests` (`44` files, `73857` assertions, `0` failures on current
main `f4d0e410e4`). `bd orphans --label lane:pandoc` was used for stale-open
cleanup, but only main-ancestor referenced commits were closed. No Pandoc binary,
office suite, TeX/Typst engine, browser engine, Node tooling, or external
validator was invoked.
