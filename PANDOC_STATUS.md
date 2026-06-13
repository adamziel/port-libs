# Pandoc Status

Last reconciled: 2026-06-13 UTC after inline attribute writer handoff on base `d3ed225156`, following JSON/native note label sidecars and CSV/TSV direct text reader coverage.

This file is the compact status companion to `progress.md`,
`lanes/pandoc/lane-status.json`, and
`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.

## Overall Ship Gate

| Check | Evidence | Verdict |
| --- | --- | --- |
| Upstream denominator | Static Pandoc upstream inventory covers 2,276 test/data/benchmark artifacts from `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`. The input-format burn-down tracks 51 upstream input tokens, with IPYNB skipped for this phase and 50 tokens in scope. | Denominator accepted for native PHP progress accounting; this is not upstream runner parity. |
| Local passing numerator | `lanes/pandoc/lane-status.json` reports 3,309 PHP passes / 0 failures. `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` reports 3,269 mapped upstream cases. | PHP lane remains green. |
| Percent | 3,269 / 2,276 mapped upstream inventory rows = 143.6%. Percentages above 100% mean local PHP slices are more granular than upstream inventory rows. | High coverage, not global ship-ready. |
| Latest mapped slice | WordPress semantic inline/math attrs and LaTeX semantic/code/math id anchors are preserved in native PHP writer handoff on top of JSON/native note label sidecars, CSV/TSV direct text reader coverage, nested-list fixture round-trip, ODT configuration metadata, nested fenced Divs, footnote label anchors, adjacent-list separators, linked-resource handoff, table-caption writer, raw HTML writer boundary, PDF/Typst package dependency, plain-writer table-row, and JSON/native sidecar slices. | One cross-format inline writer gap closed. |
| Shippable input formats | ODF/ODT is marked ship-ready: 50 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, 250.0%, with 0 critical ODF/ODT gaps. | ODF/ODT can ship under the current native PHP/no-external-validator policy. |
| Remaining critical gaps | 18 input tokens remain partial and 31 remain unsupported across DOCX/OpenXML, EPUB3, shared ZIP/OPC dependencies, JSON/native AST, CSV/TSV, CSL/BibTeX/BibLaTeX/csljson, HTML/XML/JATS DOM, LaTeX/TeX/math, Typst, PPTX/XLSX, and wiki/roff/text readers. | Pandoc as a whole is not ship-ready. |
| Stale assigned-open cleanup | `bd orphans --label lane:pandoc` was filtered to commits that are ancestors of `origin/main`. Only `plib-qka5o` qualified and was closed as already landed. A follow-up check found 0 open/in-progress Pandoc orphans whose referenced commits are on `origin/main`. Branch-only orphan candidates were left open. | Dashboard queue state is reconciled to landed work. |
| Verification | `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `git diff --check -- progress.md PANDOC_STATUS.md lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/src/PandocJsonReader.php lanes/pandoc/src/NativeReader.php lanes/pandoc/src/PandocJsonWriter.php lanes/pandoc/src/NativeWriter.php lanes/pandoc/src/DelimitedTextReader.php lanes/pandoc/src/PandocFormatRegistry.php lanes/pandoc/src/WordPressBlockWriter.php lanes/pandoc/src/LatexWriter.php lanes/pandoc/tests/PandocJsonNativeAstTest.php lanes/pandoc/tests/DelimitedTextReaderTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php lanes/pandoc/tests/MarkdownReaderTest.php lanes/pandoc/tests/LatexWriterTest.php`, syntax checks for the JSON/native, CSV/TSV, registry, and inline writer files, focused `PandocJsonNativeAstTest.php` (`1` file, `1978` assertions, `0` failures), focused `DelimitedTextReaderTest.php` plus `PandocFormatRegistryTest.php` (`2` files, `1055` assertions, `0` failures), focused writer tests (`2` files, `6650` assertions, `0` failures), focused MediaBag test (`1` file, `144` assertions, `0` failures), focused footnote tests (`2` files, `11946` assertions, `0` failures), and `php tools/run-tests.php lanes/pandoc/tests` passed. | 45 test files, 74,228 assertions, 0 failures. |
| External validators | No Pandoc binary, JSON filters, spreadsheet application, office suite, TeX/Typst engine, browser engine, Node tooling, online service, live provider test, or external validator was used for this reconciliation. | Policy satisfied. |

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
