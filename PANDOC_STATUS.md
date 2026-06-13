# Pandoc Status

Last reconciled: 2026-06-13 UTC on landed `main` `17c91bad52`, plus refinery queue snapshot at 02:20 UTC. The latest landed Pandoc code slice is LaTeX table body-head row preservation after JSON/native nullary helper payload validation.

This file is the compact status companion to `progress.md`,
`lanes/pandoc/lane-status.json`, and
`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.

## Overall Ship Gate

| Check | Evidence | Verdict |
| --- | --- | --- |
| Upstream denominator | Static Pandoc upstream inventory covers 2,276 test/data/benchmark artifacts from `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`. The input-format burn-down tracks 51 upstream input tokens, with IPYNB skipped for this phase and 50 tokens in scope. | Denominator accepted for native PHP progress accounting; this is not upstream runner parity. |
| Local passing numerator | `lanes/pandoc/lane-status.json` reports 3,314 PHP passes / 0 failures. `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` reports 3,273 mapped upstream cases. | PHP lane remains green. |
| Percent | 3,273 / 2,276 mapped upstream inventory rows = 143.8%. Percentages above 100% mean local PHP slices are more granular than upstream inventory rows. | High coverage, not global ship-ready. |
| Shippable input formats | ODF/ODT is marked ship-ready: 50 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, 250.0%, with 0 critical ODF/ODT gaps. | ODF/ODT can ship under the current native PHP/no-external-validator policy. |
| Remaining critical gaps | 18 input tokens remain partial and 31 remain unsupported across DOCX/OpenXML, EPUB3, shared ZIP/OPC dependencies, JSON/native AST, CSV/TSV, CSL/BibTeX/BibLaTeX/csljson, HTML/XML/JATS DOM, LaTeX/TeX/math, Typst, PPTX/XLSX, and wiki/roff/text readers. | Pandoc as a whole is not ship-ready. |
| Stale assigned-open cleanup | `bd orphans --label lane:pandoc` was filtered to commits that are ancestors of `origin/main`. Only `plib-qka5o` qualified and was closed as already landed. A follow-up check found 0 open/in-progress Pandoc orphans whose referenced commits are on `origin/main`. Branch-only orphan candidates were left open. | Dashboard queue state is reconciled to landed work. |
| Verification | Latest landed code-slice verification from `17c91bad52`: `jq empty`, `git diff --check`, syntax checks for the touched LaTeX/table files, focused `LatexWriterTest.php` and `TableGeometryTest.php`, focused `TableGeometryReaderHandoffTest.php`, the table geometry handoff self-test, and full `php tools/run-tests.php lanes/pandoc/tests` passed. This progress-only queue reconciliation checked `jq empty` and `git diff --check` on the status artifacts. | 45 test files, 74,336 assertions, 0 failures for the landed code slice; progress-only validation passed. |
| External validators | No Pandoc binary, Cabal/Haskell runner, office suite, TeX/Typst engine, browser engine, Node tooling, online service, live provider test, or external validator was used for this reconciliation. | Policy satisfied. |

## Closure Wave Snapshot

Current landed `main` is reconciled through `17c91bad52`: 3,314 PHP passes / 0 failures and 3,273 mapped upstream cases. Closed beads or ready MRs after `17c91bad52` are not counted below until the refinery lands them. The recent landed closure wave includes the formerly pending CSV/TSV, MediaBag, Markdown block-boundary, notes/references, LaTeX table-foot, JSON/native Div Plain, PDF/Typst package dependency conflict-policy, JSON/native task-list checkbox sidecar, JSON/native nullary helper payload, and LaTeX table body-head row evidence. The landed code slice was checked with `jq empty`, `git diff --check`, focused `LatexWriterTest.php` plus `TableGeometryTest.php` (`2` files, `1917` assertions, `0` failures), focused `TableGeometryReaderHandoffTest.php` (`1` file, `1493` assertions, `0` failures), the table geometry handoff self-test, and full `lanes/pandoc/tests` (`45` files, `74336` assertions, `0` failures).

| Surface | Landed evidence | Verdict |
| --- | --- | --- |
| CSV/TSV direct readers | `DelimitedTextReaderTest.php` plus format registry focused run: 2 files, 1,055 assertions, 0 failures. | CSV/TSV moved from unsupported to partial; broader reader parity remains. |
| Media linked-resource handoff | `MediaBagTest.php`: 144 assertions, 0 failures. | Cross-format resource handoff covered; not an input-format ship gate. |
| Markdown block boundaries | Nested fenced Div and fixture nested-list round-trip slices landed with focused `MarkdownReaderTest.php` runs. | Markdown family remains partial after bounded block-structure coverage. |
| Notes/references | Footnote label anchors and JSON/native `Note` label sidecars landed with focused Markdown/citation and JSON/native coverage. | Notes evidence improved; broader references parity remains. |
| Table geometry | LaTeX `table_foot` rows now emit longtable footer sections and body-local `headRows` render in body position; focused LaTeX/table run passed 1,917 assertions. | Table-foot and body-head-row slices covered; multi-body semantics and rowspan output gaps remain. |
| JSON/native block boundaries | Native `Div` adjacent `Plain` block boundaries now survive WordPress handoff; focused JSON/native run passed 1,987 assertions. | Bounded block-boundary slice covered; JSON/native remains partial. |
| PDF/Typst dependency policy | Typst package dependency conflict policy records sidecar package input counts, metadata-only byte exposure, non-executed network policy, package coordinates, namespace counts, and multi-version conflicts; focused `PdfEngineHandoffTest.php` passed 2,225 assertions. | Bounded no-engine provenance covered; real PDF/Typst output parity remains unsupported without external engines. |
| JSON/native task lists | `taskChecked` sidecars now preserve unchecked, checked, and nested task-list state through JSON/native readers and writers into Markdown, WordPress, and LaTeX handoff; focused `PandocJsonNativeAstTest.php` passed 2,015 assertions. | Bounded list-semantics sidecar slice covered; broader JSON/native constructor parity remains partial. |
| JSON/native nullary helpers | Stale non-empty nullary helper constructor payloads now regenerate through JSON/native writers while preserving sidecars; focused `PandocJsonNativeAstTest.php` passed 2,097 assertions. | Bounded helper-constructor payload slice covered; broader JSON/native constructor parity remains partial. |

## Landed vs Pending Matrix

| Evidence class | Items | Accounting decision |
| --- | --- | --- |
| Landed on `main` | Current baseline `17c91bad52` / `plib-actvg`, plus prior `plib-dkre4` and closure-wave rows above. | Counted in 3,314 PHP passes / 0 failures and 3,273 mapped upstream cases. |
| Ready refinery MRs, not landed | `plib-2bhzx`, `plib-ak0v4`, `plib-joe0x`, `plib-b8ozb`, `plib-gljei`, `plib-xc5vm`, `plib-yaih5`, `plib-ej919`, `plib-pb36i`, `plib-ail3e`, `plib-ot0qk`, `plib-x3ybs`, `plib-3qia0`, `plib-tdkjh`, `plib-mwy8i`, `plib-euvjd`, `plib-z3szk`, `plib-rezzc`, `plib-1awy4`, `plib-j5tip`, `plib-rs3xt`, `plib-15bdf`, `plib-mcyru`, `plib-t5oio`, `plib-kdqol`. | Treat as pending MR evidence only; do not upgrade format status or counters until the refinery merges each MR. |
| Blocked MRs | `gt refinery blocked` reported none; `gt mq list port_libs --verify` reported all 25 pending branches as ready/OK. | No blocked recovery bead required from this reconciliation. |
| High-risk recovery watch | Most pending MRs touch `progress.md`, `PANDOC_STATUS.md`, `lane-status.json`, and/or `UPSTREAM_TEST_MANIFEST.json`; `plib-gljei` is a progress reconciliation MR with no `pre_verified` flag. | Expect status-metadata rebase conflicts as the refinery drains. If rejected, recover by rebasing the source branch onto current `main` and replaying only landed-vs-pending status text. |

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
