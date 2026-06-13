# Pandoc Status

Last reconciled: 2026-06-13 UTC after Markdown header-to-section Div mapping on base `d099006810`, following JSON/native Div Plain block boundaries.

This file is the compact status companion to `progress.md`,
`lanes/pandoc/lane-status.json`, and
`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.

## Overall Ship Gate

| Check | Evidence | Verdict |
| --- | --- | --- |
| Upstream denominator | Static Pandoc upstream inventory covers 2,276 test/data/benchmark artifacts from `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`. The input-format burn-down tracks 51 upstream input tokens, with IPYNB skipped for this phase and 50 tokens in scope. | Denominator accepted for native PHP progress accounting; this is not upstream runner parity. |
| Local passing numerator | `lanes/pandoc/lane-status.json` reports 3,311 PHP passes / 0 failures. `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` reports 3,271 mapped upstream cases. | PHP lane remains green. |
| Percent | 3,271 / 2,276 mapped upstream inventory rows = 143.7%. Percentages above 100% mean local PHP slices are more granular than upstream inventory rows. | High coverage, not global ship-ready. |
| Shippable input formats | ODF/ODT is marked ship-ready: 50 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, 250.0%, with 0 critical ODF/ODT gaps. | ODF/ODT can ship under the current native PHP/no-external-validator policy. |
| Remaining critical gaps | 18 input tokens remain partial and 31 remain unsupported across DOCX/OpenXML, EPUB3, shared ZIP/OPC dependencies, JSON/native AST, CSV/TSV, CSL/BibTeX/BibLaTeX/csljson, HTML/XML/JATS DOM, LaTeX/TeX/math, Typst, PPTX/XLSX, and wiki/roff/text readers. | Pandoc as a whole is not ship-ready. |
| Stale assigned-open cleanup | `bd orphans --label lane:pandoc` was filtered to commits that are ancestors of `origin/main`. Only `plib-qka5o` qualified and was closed as already landed. A follow-up check found 0 open/in-progress Pandoc orphans whose referenced commits are on `origin/main`. Branch-only orphan candidates were left open. | Dashboard queue state is reconciled to landed work. |
| Verification | `jq empty`, `git diff --check`, syntax checks for `MarkdownReader.php` and `MarkdownReaderTest.php`, focused `MarkdownReaderTest.php`, and `php tools/run-tests.php lanes/pandoc/tests` passed. | 45 test files, 74,231 assertions, 0 failures. |
| External validators | No Pandoc binary, Cabal/Haskell runner, office suite, TeX/Typst engine, browser engine, Node tooling, online service, live provider test, or external validator was used for this reconciliation. | Policy satisfied. |

## Closure Wave Snapshot

This branch is reconciled on base `d099006810`: 3,311 PHP passes / 0 failures and 3,271 mapped upstream cases. The recent closure wave landed the formerly pending CSV/TSV, MediaBag, Markdown block-boundary, notes/references, LaTeX table-foot, JSON/native Div Plain, and Markdown header-to-section evidence. This slice was checked with syntax checks for `MarkdownReader.php` and `MarkdownReaderTest.php`, focused `MarkdownReaderTest.php` (`1` file, `6643` assertions, `0` failures), and full `lanes/pandoc/tests` (`45` files, `74231` assertions, `0` failures).

| Surface | Landed evidence | Verdict |
| --- | --- | --- |
| CSV/TSV direct readers | `DelimitedTextReaderTest.php` plus format registry focused run: 2 files, 1,055 assertions, 0 failures. | CSV/TSV moved from unsupported to partial; broader reader parity remains. |
| Media linked-resource handoff | `MediaBagTest.php`: 144 assertions, 0 failures. | Cross-format resource handoff covered; not an input-format ship gate. |
| Markdown block boundaries | Nested fenced Div and fixture nested-list round-trip slices landed with focused `MarkdownReaderTest.php` runs. | Markdown family remains partial after bounded block-structure coverage. |
| Notes/references | Footnote label anchors and JSON/native `Note` label sidecars landed with focused Markdown/citation and JSON/native coverage. | Notes evidence improved; broader references parity remains. |
| Table geometry | LaTeX `table_foot` rows now emit longtable footer sections; focused LaTeX/table run passed 1,917 assertions. | Table-foot slice covered; row/body/span table gaps remain. |
| JSON/native block boundaries | Native `Div` adjacent `Plain` block boundaries now survive WordPress handoff; focused JSON/native run passed 1,987 assertions. | Bounded block-boundary slice covered; JSON/native remains partial. |
| Markdown header sections | `MarkdownReader` opt-in `sectionDivs` mode maps heading runs into nested `.section .levelN` Div wrappers; focused Markdown run passed 6,643 assertions. | Header-to-section mapping slice covered; broader fenced section variant parity remains. |

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
