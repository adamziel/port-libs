# Pandoc Citation/CSL Given-Name Disambiguation Handoff

Slice: `pandoc-citation-csl-core-current-base-20260606T104209Z`
Base: `7f195d9c47bea735e93265bb1a8576f4ad10d3fa`
Date: 2026-06-06 UTC

## Behavior

This slice adds bounded native PHP support for CSL citation options
`disambiguate-add-givenname` and `givenname-disambiguation-rule`.
`CslStyle` now validates the allowed rule names and exposes them in style
summaries. `CitationCslProcessor` marks ambiguous citation nodes with either
initial or full given-name expansion before rendering citation labels:

- default `by-cite` groups ambiguous author-year cites;
- `primary-name` and `primary-name-with-initials` group rendered primary names
  across the document;
- `all-names` and `all-names-with-initials` are parsed and use the same bounded
  rule selection while leaving hidden-name expansion for a later slice.

Source truth: CSL 1.0.2 disambiguation method (1) expands names before
`disambiguate` choose branches and year suffixes, with `by-cite` as the default
given-name disambiguation rule.

Reference: https://docs.citationstyles.org/en/v1.0.2/specification.html

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note existed
  for this session.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 1635 assertions, 0 failures`.
- Red-first focused command failed after adding expectations:
  `1 test files, 1636 assertions, 1 failures`, because
  `disambiguate-add-givenname` was not parsed or exposed.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 1647 assertions, 0 failures`.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-citation-csl-givenname-disambiguation-handoff.php --self-test`
  passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1299 -> 1300`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`:
  `1713 -> 1714`.
- `mappedCitationCslCoreCases`: `10 -> 11`.
- Focused CitationCslProcessor coverage: `1635 -> 1647` assertions.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `CslStyle`,
`CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, the focused
PHP test harness, and a lane-local WordPress handoff example.

Upstream runner parity remains blocked on a hydrated Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` plus Cabal project/package files
and Haskell Tasty executable builds for `test-pandoc` and
`test-pandoc-lua-engine`.

## Non-Overlap

This patch avoids prior Citation/CSL slices for date parts, uncertain-date
predicates, locator/page labels, et-al rendering, et-al-subsequent,
et-al-use-last, bibliography subsequent-author substitution, and
`disambiguate="true"` choose conditions. It does not implement
`disambiguate-add-names`, abbreviation-list lookup, note-style output,
page-range collapsing, broader locale/style XML behavior, or upstream runner
parity.
