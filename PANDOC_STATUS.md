# Pandoc Status

Last reconciled: 2026-06-13 UTC after Markdown fixture nested-list round-trip landed on `43dbb735c4`.

This file is the compact status companion to `progress.md`,
`lanes/pandoc/lane-status.json`, and
`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.

## Overall Ship Gate

| Check | Evidence | Verdict |
| --- | --- | --- |
| Upstream denominator | Static Pandoc upstream inventory covers 2,276 test/data/benchmark artifacts from `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`. The input-format burn-down tracks 51 upstream input tokens, with IPYNB skipped for this phase and 50 tokens in scope. | Denominator accepted for native PHP progress accounting; this is not upstream runner parity. |
| Local passing numerator | `lanes/pandoc/lane-status.json` reports 3,305 PHP passes / 0 failures. `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` reports 3,265 mapped upstream cases. | PHP lane remains green. |
| Percent | 3,265 / 2,276 mapped upstream inventory rows = 143.5%. Percentages above 100% mean local PHP slices are more granular than upstream inventory rows. | High coverage, not global ship-ready. |
| Shippable input formats | ODF/ODT is marked ship-ready: 50 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, 250.0%, with 0 critical ODF/ODT gaps. | ODF/ODT can ship under the current native PHP/no-external-validator policy. |
| Remaining critical gaps | 16 input tokens remain partial and 33 remain unsupported across DOCX/OpenXML, EPUB3, shared ZIP/OPC dependencies, JSON/native AST, CSL/BibTeX/BibLaTeX/csljson, HTML/XML/JATS DOM, LaTeX/TeX/math, Typst, PPTX/XLSX, wiki/roff/text readers, and CSV/TSV. | Pandoc as a whole is not ship-ready. |
| Closure-wave split | Landed main includes base PDF/Typst package policy, raw HTML writer boundaries, JSON/native table caption block writers, MediaBag linked resources, Markdown adjacent-list separators, notes label recovery, nested fenced Divs, ODT configuration metadata, and mixed nested-list round trip. CSV/TSV 2/2, notes follow-up sidecars, Typst/PDF policy follow-up, task-list sidecars, and table-foot geometry are closed-bead/open-MR evidence until their commits land. | Shipped and pending evidence are separate. |
| Stale assigned-open cleanup | `bd orphans --label lane:pandoc` was filtered to commits that are ancestors of `origin/main`. Only `plib-qka5o` qualified and was closed as already landed. A follow-up check found 0 open/in-progress Pandoc orphans whose referenced commits are on `origin/main`. Branch-only orphan candidates were left open. | Dashboard queue state is reconciled to landed work. |
| Verification | This reconciliation passed `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `git diff --check -- progress.md PANDOC_STATUS.md lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, focused `MediaBagTest.php`, focused `MarkdownReaderTest.php`, and focused `OpenDocumentPackageTest.php`. The latest landed source branches also carried full-suite evidence. | Current lightweight gate passed; latest source-branch full gate was 44 test files, 74,167 assertions, 0 failures. |
| External validators | No Pandoc binary, office suite, TeX/Typst engine, browser engine, Node tooling, online service, live provider test, or external validator was used for this reconciliation. | Policy satisfied. |

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

## Closure-Wave Ledger

Landed counters on `origin/main` at `43dbb735c4`: 3,305 PHP passes / 0 failures and 3,265 / 2,276 mapped upstream cases. Closed beads with open merge-request beads are pending evidence only until their commits land.

| Item | Landed status | Pending evidence |
| --- | --- | --- |
| CSV/TSV 2/2 | Not landed; tabular readers remain 0 / 2. | Open MR `plib-wisp-60w` at `1e619e4785`. |
| MediaBag resources | Landed at `6c77acbadb`; phpPass 3,299 -> 3,300 and mapped cases 3,259 -> 3,260. | No pending MR for this slice. |
| Markdown adjacent-list separators | Landed at `659cae59e4`; phpPass 3,300 -> 3,301 and mapped cases 3,260 -> 3,261. | No pending MR for this slice. |
| Notes label recovery | Landed at `04585158b3`; phpPass 3,301 -> 3,302 and mapped cases 3,261 -> 3,262. | Follow-up MR `plib-wisp-b5c` at `e965a30c12`. |
| Nested fenced Divs | Landed at `e9fb37d55a`; phpPass 3,302 -> 3,303 and mapped cases 3,262 -> 3,263. | No pending MR for this slice. |
| ODT configuration package metadata | Landed at `4c296642da`; phpPass 3,303 -> 3,304 and mapped cases 3,263 -> 3,264. | Not a closure-wave MR; included in current landed counters. |
| Mixed nested-list round trip | Landed at `43dbb735c4`; phpPass 3,304 -> 3,305 and mapped cases 3,264 -> 3,265. | No pending MR for this slice. |
| Typst/PDF package policy follow-up | Base policy landed; follow-up not landed. | Open MR `plib-wisp-od2` at `49427c9ca3`. |
| Task-list sidecars | Not landed. | Open MR `plib-wisp-f0m` at `914eaec344`. |
| Table geometry continuation | Base table caption writers landed; table-foot geometry not landed. | Open MR `plib-wisp-8wv` at `d899f30297`. |

## Shipping Call

The progress dashboard is reconciled, and ODF/ODT is ship-ready with evidence.
The full Pandoc native PHP input lane remains active because most input-token
families still have partial or unsupported coverage. The next work should stay
inside the existing format-closure and core-blocker gates, using focused tests
and the full `lanes/pandoc/tests` gate before submission.
