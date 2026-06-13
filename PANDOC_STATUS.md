# Pandoc Status

Last reconciled: 2026-06-13 UTC after text markup unsupported diagnostics on base `9ab2f42c1e`, following shared ZIP/OPC selected-entry handoff role buckets.

This file is the compact status companion to `progress.md`,
`lanes/pandoc/lane-status.json`, and
`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.

## Overall Ship Gate

| Check | Evidence | Verdict |
| --- | --- | --- |
| Upstream denominator | Static Pandoc upstream inventory covers 2,276 test/data/benchmark artifacts from `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`. The input-format burn-down tracks 51 upstream input tokens, with IPYNB skipped for this phase and 50 tokens in scope. | Denominator accepted for native PHP progress accounting; this is not upstream runner parity. |
| Local passing numerator | `lanes/pandoc/lane-status.json` reports 3,333 PHP passes / 0 failures. `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` reports 3,292 mapped upstream cases. | PHP lane remains green. |
| Percent | 3,292 / 2,276 mapped upstream inventory rows = 144.6%. Percentages above 100% mean local PHP slices are more granular than upstream inventory rows. | High coverage, not global ship-ready. |
| Shippable input formats | ODF/ODT is marked ship-ready: 50 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, 250.0%, with 0 critical ODF/ODT gaps. | ODF/ODT can ship under the current native PHP/no-external-validator policy. |
| Remaining critical gaps | 18 input tokens remain partial and 31 remain unsupported across DOCX/OpenXML, EPUB3, shared ZIP/OPC dependencies, JSON/native AST, CSV/TSV, CSL/BibTeX/BibLaTeX/csljson, HTML/XML/JATS DOM, LaTeX/TeX/math, Typst, PPTX/XLSX, and wiki/roff/text readers. Text markup unsupported diagnostics, shared ZIP selected-entry role buckets, EPUB XHTML table semantics, PlainWriter table body-group boundaries, Markdown HTML list item attribute handoff, Markdown math attribute round-trip, JSON/native block-container mixed-content flushing, XML/JATS/BITS direct reader capability diagnostics, Markdown header section Div mapping, Markdown table-cell note placement, DOCX/OpenXML subdocument diagnostics, definition-list term-group handoff, Markdown fenced Div section-reference boundaries, inline writer attributes, EPUB direct manifest/spine diagnostics, EPUB XHTML definition-list handoff, and text markup unsupported ship-gate accounting are covered. | Pandoc as a whole is not ship-ready. |
| Stale assigned-open cleanup | `bd orphans --label lane:pandoc` was filtered to commits that are ancestors of `origin/main`. Only `plib-qka5o` qualified and was closed as already landed. A follow-up check found 0 open/in-progress Pandoc orphans whose referenced commits are on `origin/main`. Branch-only orphan candidates were left open. | Dashboard queue state is reconciled to landed work. |
| Verification | `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `git diff --check -- progress.md PANDOC_STATUS.md lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/src/PandocFormatRegistry.php lanes/pandoc/tests/PandocFormatRegistryTest.php`, syntax checks for the touched registry files, focused `PandocFormatRegistryTest.php`, and `php tools/run-tests.php lanes/pandoc/tests` passed. | 45 test files, 75,012 assertions, 0 failures. |
| External validators | No Pandoc binary, Cabal/Haskell runner, office suite, TeX/Typst engine, browser engine, Node tooling, online service, live provider test, or external validator was used for this reconciliation. | Policy satisfied. |

## Closure Wave Snapshot

Current `main` is reconciled through the text markup unsupported diagnostics slice rebased on `9ab2f42c1e`: 3,333 PHP passes / 0 failures and 3,292 mapped upstream cases. The recent closure wave landed the formerly pending CSV/TSV, MediaBag, Markdown block-boundary, escaped-attribute, fenced Div section-reference, definition-list term-group, table-cell note placement, header section Div evidence, text markup unsupported diagnostics, shared ZIP selected-entry role buckets, EPUB XHTML table semantics, PlainWriter table body-group boundaries, Markdown HTML list item attribute handoff, Markdown math attribute round-trip, JSON/native block-container mixed-content flushing, XML/JATS/BITS direct reader capability diagnostics, notes/references backlink metadata, DOCX/OpenXML subdocument diagnostics, EPUB direct manifest/spine diagnostics, EPUB XHTML definition-list handoff, inline writer attribute handoff, text markup reader unsupported ship-gate accounting, LaTeX table-foot, JSON/native Div Plain, PDF/Typst package dependency conflict-policy, JSON/native task-list checkbox sidecar, JSON/native nullary helper payload, and LaTeX table body-head row evidence. This refresh was checked with `jq empty`, `git diff --check`, syntax checks for the touched registry files, focused `PandocFormatRegistryTest.php` (`1` file, `1349` assertions, `0` failures), and full `lanes/pandoc/tests` (`45` files, `75012` assertions, `0` failures).

| Surface | Landed evidence | Verdict |
| --- | --- | --- |
| CSV/TSV direct readers | `DelimitedTextReaderTest.php` plus format registry focused run: 2 files, 1,055 assertions, 0 failures. | CSV/TSV moved from unsupported to partial; broader reader parity remains. |
| DOCX/OpenXML package reader | Subdocument relationship diagnostics now report referenced/unreferenced, external/internal, missing, wrong-type, unknown-id, missing-id, query/fragment suffix, content-type/hash, and unsupported expansion metadata in `DocxOpenXmlReaderTest.php`. | DOCX evidence improved to 93 local cases; broader DOCX/OpenXML package parity remains partial. |
| EPUB direct package reader | Manifest query/fragment suffix diagnostics, external/missing spine readability reports, XHTML definition-list handoff, and bounded XHTML table-section review metadata landed in EPUB reader coverage. | EPUB evidence improved to 62 local cases; broader EPUB3 package parity remains partial. |
| Shared ZIP/OPC package | `ZipPackage::entryHandoffPreflight()` role summaries now expose unique readable selected entries, handoff byte totals, handoff entry names, and issue counts by semantic role. | Shared ZIP/OPC evidence improved to 107 local cases; full package parity remains partial. |
| Media linked-resource handoff | `MediaBagTest.php`: 144 assertions, 0 failures. | Cross-format resource handoff covered; not an input-format ship gate. |
| Markdown/list/note boundaries | Nested fenced Div, escaped fenced-Div attributes, fenced Div section-reference boundaries, fixture nested-list round-trip, definition-list term-group, table-cell note placement, and header section Div slices landed with focused Markdown/JSON-native runs. | Markdown family remains partial after bounded block/list/note/section coverage. |
| Markdown math attributes | `MarkdownReaderTest.php` now covers immediate `{#id .class key="value"}` tuples after inline and display math, with writer round-trip preservation. | Bounded inline math attribute round-trip covered; broader inline writer parity remains partial. |
| Markdown HTML list item attributes | `MarkdownReaderTest.php` now covers safe `li` id/class/data/title attributes from HTML list input through WordPress list item output while unsafe style/event attrs stay filtered. | Bounded HTML reader to WordPress list-item attribute handoff covered; broader list parity remains partial. |
| Notes/references | Footnote label anchors, WordPress backlink metadata, table-cell note placement, and JSON/native `Note` label sidecars landed with focused Markdown/DOCX/citation and JSON/native coverage. | Notes evidence improved; broader references parity remains. |
| Inline writer attributes | WordPress semantic/math inline attrs and LaTeX semantic/code/math id anchors landed with focused Markdown/LaTeX writer coverage. | Bounded writer handoff covered; broader reader/format parity remains partial. |
| Format registry/text readers | The 20 wiki/roff/man/text markup reader input tokens now have executable unsupported ship-gate accounting plus reason-code, family-bucket, direction, and reader/writer capability diagnostics in `PandocFormatRegistryTest.php`. | Explicitly unsupported; first native reader implementation still required. |
| XML/JATS/BITS direct reader capability | `PandocFormatRegistryTest.php` and `XmlHtmlDomTest.php` now make `xml`, `jats`, and `bits` explicit unsupported direct-reader inputs with bounded diagnostic packets and `directReaderParity=false`. | Capability status is explicit; full XML/JATS/BITS readers remain unsupported. |
| Table geometry | LaTeX `table_foot` rows now emit longtable footer sections, body-local `headRows` render in body position, and PlainWriter separates consecutive native table body groups with blank lines. | Table-foot, body-head-row, and plain-text body-group slices covered; Markdown/AsciiDoc/LaTeX body-group semantics and rowspan output gaps remain. |
| JSON/native block and list handoff | Native `Div` adjacent `Plain` block boundaries survive WordPress handoff, and native `definition_term` linebreaks now render through WordPress definition lists; latest focused Markdown/JSON-native run passed 8,762 assertions. | Bounded block/list handoff slices covered; JSON/native remains partial. |
| JSON/native mixed block containers | `PandocJsonNativeAstTest.php` now covers mixed inline runs around nested blocks inside `BlockQuote`, `Div`, `Note`, and shared child-block payloads. | Bounded mixed-content fixture slice covered; broader JSON/native fixture parity remains partial. |
| PDF/Typst dependency policy | Typst package dependency conflict policy records sidecar package input counts, metadata-only byte exposure, non-executed network policy, package coordinates, namespace counts, and multi-version conflicts; focused `PdfEngineHandoffTest.php` passed 2,225 assertions. | Bounded no-engine provenance covered; real PDF/Typst output parity remains unsupported without external engines. |
| JSON/native task lists | `taskChecked` sidecars now preserve unchecked, checked, and nested task-list state through JSON/native readers and writers into Markdown, WordPress, and LaTeX handoff; focused `PandocJsonNativeAstTest.php` passed 2,015 assertions. | Bounded list-semantics sidecar slice covered; broader JSON/native constructor parity remains partial. |
| JSON/native nullary helpers | Stale non-empty nullary helper constructor payloads now regenerate through JSON/native writers while preserving sidecars; focused `PandocJsonNativeAstTest.php` passed 2,097 assertions. | Bounded helper-constructor payload slice covered; broader JSON/native constructor parity remains partial. |

## Queue Snapshot

Open core-blocker slices still exist and should continue as targeted, current-base
work rather than broad duplicate timestamp slices:

| Family | Open slices after cleanup |
| --- | ---: |
| DOCX/OpenXML package ingestion | 9 |
| EPUB3 package ingestion | 15 |
| JSON/native AST constructor completeness | 10 |
| ODF/ODT OpenDocument package ingestion | 11 |
| PDF/Typst boundary provenance | 16 |
| XML/HTML5 DOM | 15 |
| Citation/bibliography CSL | 20 |
| Shared ZIP/OPC package | 12 |

## Shipping Call

The progress dashboard is reconciled, and ODF/ODT is ship-ready with evidence.
The full Pandoc native PHP input lane remains active because most input-token
families still have partial or unsupported coverage. The next work should stay
inside the existing format-closure and core-blocker gates, using focused tests
and the full `lanes/pandoc/tests` gate before submission.
