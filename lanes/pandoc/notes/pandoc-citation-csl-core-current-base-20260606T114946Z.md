# Pandoc Citation/CSL Visible Label Disambiguation Handoff

Slice: `pandoc-citation-csl-core-current-base-20260606T114946Z`
Base: `7b9b6e5a2c6885b2398accee1db59fa1d0384094`
Date: 2026-06-06 UTC

## Behavior

This slice fixes bounded native PHP CSL disambiguation when a custom citation
layout already renders distinct visible `citation-label` values. Previously,
`disambiguate-add-year-suffix="true"` grouped only by hidden author-year
metadata, so two sources with the same author and issued year rendered as
`WP-POSTa` and `WP-MEDIAb` even though the style output was already distinct.

`CitationCslProcessor` now computes the disambiguation key from the custom
citation layout rendered with an empty `year-suffix` before falling back to the
accepted author-year key. This keeps true author-year ambiguity on the existing
year-suffix path while preventing unnecessary suffixes and `cslDisambiguate`
markers for visibly unique citation-label output.

Source truth: CSL disambiguation is based on rendered citation ambiguity, and
`year-suffix` is one of the variables styles can place only when needed by
`disambiguate-add-year-suffix`. This lane keeps the behavior bounded to native
PHP style rendering and does not invoke citeproc.

Reference: https://docs.citationstyles.org/en/v1.0.2/specification.html

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note existed
  for this session.
- Red-first focused command after adding expectations:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed with `1 test files, 1668 assertions, 1 failures` because the label
  cluster rendered `[WP-POSTa; WP-MEDIAb]`.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 1679 assertions, 0 failures`.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-citation-csl-label-disambiguation-handoff.php --self-test`
  passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1320 -> 1321`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`:
  `1734 -> 1735`.
- Focused CitationCslProcessor coverage: `1663 -> 1679` assertions, a net
  `+16` assertions from this test case.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `CslStyle`,
`CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, the focused
PHP test harness, and a lane-local WordPress handoff example.

Upstream runner parity remains blocked on a hydrated Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` plus Cabal project/package files
and Haskell Tasty executable builds for `test-pandoc` and
`test-pandoc-lua-engine`.

## Non-Overlap

This patch avoids prior Citation/CSL slices for date parts, date forms,
uncertain-date predicates, locator/page labels, number rendering, citation
position/near-note conditions, et-al rendering, et-al-subsequent,
et-al-use-last, given-name disambiguation, `disambiguate="true"` parsing,
bibliography subsequent-author substitution, year-suffix collapse, and
BibTeX/BibLaTeX metadata parsing.

It does not implement full citeproc retry loops, `disambiguate-add-names`,
abbreviation-list lookup, note-style output, page-range collapsing, or upstream
Pandoc/citeproc runner parity.
