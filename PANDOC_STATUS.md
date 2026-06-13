# Pandoc Status

Last reconciled: 2026-06-13 UTC after JSON/native nested metadata payload preservation on base `4487923aa2`, following EPUB direct manifest/spine diagnostics.

This file is the compact status companion to `progress.md`,
`lanes/pandoc/lane-status.json`, and
`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.

## Overall Ship Gate

| Check | Evidence | Verdict |
| --- | --- | --- |
| Upstream denominator | Static Pandoc upstream inventory covers 2,276 test/data/benchmark artifacts from `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`. The input-format burn-down tracks 51 upstream input tokens, with IPYNB skipped for this phase and 50 tokens in scope. | Denominator accepted for native PHP progress accounting; this is not upstream runner parity. |
| Local passing numerator | `lanes/pandoc/lane-status.json` reports 3,319 PHP passes / 0 failures. `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` reports 3,278 mapped upstream cases. | PHP lane remains green. |
| Percent | 3,278 / 2,276 mapped upstream inventory rows = 144.0%. Percentages above 100% mean local PHP slices are more granular than upstream inventory rows. | High coverage, not global ship-ready. |
| Shippable input formats | ODF/ODT is marked ship-ready: 50 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, 250.0%, with 0 critical ODF/ODT gaps. | ODF/ODT can ship under the current native PHP/no-external-validator policy. |
| Remaining critical gaps | 18 input tokens remain partial and 31 remain unsupported across DOCX/OpenXML, EPUB3, shared ZIP/OPC dependencies, JSON/native AST, CSV/TSV, CSL/BibTeX/BibLaTeX/csljson, HTML/XML/JATS DOM, LaTeX/TeX/math, Typst, PPTX/XLSX, and wiki/roff/text readers. EPUB direct manifest/spine diagnostics are covered, and the wiki/roff/man/text markup family has an explicit unsupported ship-gate verdict for all 20 tokens. | Pandoc as a whole is not ship-ready. |
| Stale assigned-open cleanup | `bd orphans --label lane:pandoc` was filtered to commits that are ancestors of `origin/main`. Only `plib-qka5o` qualified and was closed as already landed. A follow-up check found 0 open/in-progress Pandoc orphans whose referenced commits are on `origin/main`. Branch-only orphan candidates were left open. | Dashboard queue state is reconciled to landed work. |
| Verification | `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `git diff --check -- progress.md PANDOC_STATUS.md lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/src/PandocJsonWriter.php lanes/pandoc/src/NativeWriter.php lanes/pandoc/tests/PandocJsonNativeAstTest.php lanes/pandoc/notes/pandoc-json-native-nested-meta-payloads-20260613.md`, syntax checks for touched JSON/native writer files and `PandocJsonNativeAstTest.php`, focused `PandocJsonNativeAstTest.php`, and `php tools/run-tests.php lanes/pandoc/tests` passed. | 45 test files, 74,512 assertions, 0 failures. |
| External validators | No Pandoc binary, Cabal/Haskell runner, office suite, TeX/Typst engine, browser engine, Node tooling, online service, live provider test, or external validator was used for this reconciliation. | Policy satisfied. |

## Closure Wave Snapshot

Current `main` is reconciled through `4487923aa2` plus this slice: 3,319 PHP passes / 0 failures and 3,278 mapped upstream cases. The recent closure wave landed the formerly pending CSV/TSV, MediaBag, Markdown block-boundary and escaped-attribute, notes/references backlink metadata, EPUB direct manifest/spine diagnostics, text markup reader unsupported ship-gate accounting, LaTeX table-foot, JSON/native Div Plain, PDF/Typst package dependency conflict-policy, JSON/native task-list checkbox sidecar, JSON/native nullary helper payload, LaTeX table body-head row, and JSON/native nested metadata payload evidence. This refresh was checked with `jq empty`, `git diff --check`, syntax checks for the touched JSON/native files, focused `PandocJsonNativeAstTest.php` (`1` file, `2137` assertions, `0` failures), and full `lanes/pandoc/tests` (`45` files, `74512` assertions, `0` failures).

| Surface | Landed evidence | Verdict |
| --- | --- | --- |
| CSV/TSV direct readers | `DelimitedTextReaderTest.php` plus format registry focused run: 2 files, 1,055 assertions, 0 failures. | CSV/TSV moved from unsupported to partial; broader reader parity remains. |
| EPUB direct package reader | Manifest query/fragment suffix diagnostics plus external/missing spine readability reports landed in `EpubPackageReaderTest.php`. | EPUB evidence improved to 60 local cases; broader EPUB3 package parity remains partial. |
| Media linked-resource handoff | `MediaBagTest.php`: 144 assertions, 0 failures. | Cross-format resource handoff covered; not an input-format ship gate. |
| Markdown block boundaries | Nested fenced Div, escaped fenced-Div attributes, and fixture nested-list round-trip slices landed with focused `MarkdownReaderTest.php` runs. | Markdown family remains partial after bounded block-structure coverage. |
| Notes/references | Footnote label anchors, WordPress backlink metadata, and JSON/native `Note` label sidecars landed with focused Markdown/DOCX/citation and JSON/native coverage. | Notes evidence improved; broader references parity remains. |
| Format registry/text readers | The 20 wiki/roff/man/text markup reader input tokens now have executable unsupported ship-gate accounting in `PandocFormatRegistryTest.php`. | Explicitly unsupported; first native reader implementation still required. |
| Table geometry | LaTeX `table_foot` rows now emit longtable footer sections and body-local `headRows` render in body position; focused LaTeX/table run passed 1,917 assertions. | Table-foot and body-head-row slices covered; multi-body semantics and rowspan output gaps remain. |
| JSON/native block boundaries | Native `Div` adjacent `Plain` block boundaries now survive WordPress handoff; focused JSON/native run passed 1,987 assertions. | Bounded block-boundary slice covered; JSON/native remains partial. |
| PDF/Typst dependency policy | Typst package dependency conflict policy records sidecar package input counts, metadata-only byte exposure, non-executed network policy, package coordinates, namespace counts, and multi-version conflicts; focused `PdfEngineHandoffTest.php` passed 2,225 assertions. | Bounded no-engine provenance covered; real PDF/Typst output parity remains unsupported without external engines. |
| JSON/native task lists | `taskChecked` sidecars now preserve unchecked, checked, and nested task-list state through JSON/native readers and writers into Markdown, WordPress, and LaTeX handoff; focused `PandocJsonNativeAstTest.php` passed 2,015 assertions. | Bounded list-semantics sidecar slice covered; broader JSON/native constructor parity remains partial. |
| JSON/native nullary helpers | Stale non-empty nullary helper constructor payloads now regenerate through JSON/native writers while preserving sidecars; focused `PandocJsonNativeAstTest.php` passed 2,097 assertions. | Bounded helper-constructor payload slice covered; broader JSON/native constructor parity remains partial. |
| JSON/native nested metadata | Edited `MetaMap` and `MetaList` containers now preserve unchanged nested metadata native sidecars via reader-indexed provenance paths; focused `PandocJsonNativeAstTest.php` passed 2,137 assertions. | Bounded metadata sidecar slice covered; broader JSON/native constructor parity remains partial. |

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
