# Pandoc Status

Last reconciled: 2026-06-13 UTC after CSV/TSV direct text reader coverage on base `b266b6e53c`, following bounded RIS citation parsing, JSON/native target tuple sidecars, plain writer table caption rows, and PDF/Typst package dependency policy.

This file is the compact status companion to `progress.md`,
`lanes/pandoc/lane-status.json`, and
`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.

## Overall Ship Gate

| Check | Evidence | Verdict |
| --- | --- | --- |
| Upstream denominator | Static Pandoc upstream inventory covers 2,276 test/data/benchmark artifacts from `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`. The input-format burn-down tracks 51 upstream input tokens, with IPYNB skipped for this phase and 50 tokens in scope. | Denominator accepted for native PHP progress accounting; this is not upstream runner parity. |
| Local passing numerator | `lanes/pandoc/lane-status.json` reports 3,299 PHP passes / 0 failures. `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` reports 3,259 mapped upstream cases. | PHP lane remains green. |
| Percent | 3,259 / 2,276 mapped upstream inventory rows = 143.2%. Percentages above 100% mean local PHP slices are more granular than upstream inventory rows. | High coverage, not global ship-ready. |
| Shippable input formats | ODF/ODT is marked ship-ready: 49 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, 245.0%, with 0 critical ODF/ODT gaps. | ODF/ODT can ship under the current native PHP/no-external-validator policy. |
| Remaining critical gaps | 18 input tokens remain partial and 31 remain unsupported across DOCX/OpenXML, EPUB3, shared ZIP/OPC dependencies, JSON/native AST, CSL/BibTeX/BibLaTeX/csljson, HTML/XML/JATS DOM, LaTeX/TeX/math, Typst, PPTX/XLSX, wiki/roff/text readers, and remaining CSV/TSV parity. | Pandoc as a whole is not ship-ready. |
| Stale assigned-open cleanup | `bd orphans --label lane:pandoc` was filtered to commits that are ancestors of `origin/main`. Only `plib-qka5o` qualified and was closed as already landed. A follow-up check found 0 open/in-progress Pandoc orphans whose referenced commits are on `origin/main`. Branch-only orphan candidates were left open. | Dashboard queue state is reconciled to landed work. |
| Verification | `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `git diff --check`, syntax checks for the CSV/TSV reader and registry files, focused `DelimitedTextReaderTest.php` plus `PandocFormatRegistryTest.php`, and `php tools/run-tests.php lanes/pandoc/tests` passed after rebase. | 45 test files, 74,049 assertions, 0 failures. |
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

## Shipping Call

The progress dashboard is reconciled, and ODF/ODT is ship-ready with evidence.
The full Pandoc native PHP input lane remains active because most input-token
families still have partial or unsupported coverage. The next work should stay
inside the existing format-closure and core-blocker gates, using focused tests
and the full `lanes/pandoc/tests` gate before submission.
