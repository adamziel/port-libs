# Pandoc Citation/CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260606T024232Z`
Base accepted HEAD: `b16fe7b8f1a76ae151268ab15841f7714fcf2332`

## Behavior

- Added bounded native CSL bibliography rendering for compact family-name/given-name scripts under `name-as-sort-order`.
- `CitationCslProcessor` now keeps CJK family-given names in display order while Latin names still invert with the configured `sort-separator`.
- Added a WordPress review-packet example showing mixed Chinese, Japanese, and Latin bibliography names without invoking external citation tooling.

## Source Truth

- CSL name-ordering source truth is the official CSL 1.0.2 specification at <https://docs.citationstyles.org/en/v1.0.2/specification.html>: `name-as-sort-order` applies to given-name-first scripts, while family-name-first scripts retain family before given.
- This is a bounded native PHP support-library slice, not full citeproc/Pandoc runner parity.

## Evidence

- Rework note check: no `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- Red-first command: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result before implementation: `1 test files, 1484 assertions, 1 failures`
  - Failing behavior: expected `毛泽东. Chinese Review Packet. 2026.` but rendered `毛, 泽东. Chinese Review Packet. 2026.`
- Green command: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result after implementation: `1 test files, 1490 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-citation-csl-family-given-handoff.php --self-test`
  - Result: `wordpress-citation-csl-family-given-handoff self-test passed`
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1162 -> 1163`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1612 -> 1613`.
- `mappedCitationCslCoreCases`: `10 -> 11`.
- Focused `CitationCslProcessorTest.php` coverage now reports `80 PASS` cases and `1490` assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `AstNode`, and `WordPressBlockWriter` support. No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online sanitizer, online service, or live provider test was executed.

## Non-Overlap And Follow-Up

This patch does not repeat accepted date/name metadata, date-part forms, macros, choose conditionals, is-numeric or is-uncertain-date predicates, locator/page labels, number rendering, punctuation, et-al handling, delimiter-precedes-last, subsequent-author substitution, year-suffix/collapse, BibTeX/BibLaTeX parsing, PDF, DOCX, ODT, YAML, doctemplate, or table-geometry slices.

Follow-up should keep fuller script detection, locale-specific name-order overrides, institution/name formatting, note-style output, bibliography disambiguation, and full citeproc/Pandoc runner parity as separate bounded slices.
