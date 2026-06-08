# pandoc-citation-csl-core-current-base-20260608T083326Z

Base accepted HEAD: `17e7958788b5df7bc16528a3ea78ce7c5bcbf06e`

## Scope

Implemented one bounded native Citation/CSL behavior cluster: CSL
`disambiguate-add-names` now expands shortened author name lists for ambiguous
author-date citations before the existing citation layout renders `et al.`.

This is a native PHP support-library slice under `lanes/pandoc/**`; no Pandoc,
citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner,
external bibliography manager, online service, live provider test, or
live-service provider test was executed.

## Implementation

- `CslStyle` parses and validates the citation boolean attribute
  `disambiguate-add-names`, includes it in default citation options, and exposes
  it through `cslStyleSummary()`.
- `CitationCslProcessor` computes the smallest useful
  `cslDisambiguateNameCount` for ambiguous author/year groups whose rendered
  citation labels are shortened by `et al.`.
- Citation AST nodes carry the computed name-count annotation through direct
  cluster rendering and document normalization.
- The existing citation name-list renderer consumes the annotation by raising
  `et-al-use-first` for that citation only; bibliography rendering remains
  unchanged.
- Added a WordPress handoff example for review queues with colliding
  `Smith et al. 2026` source packets.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2340 assertions, 0 failures`.
- Red-first: the same focused test command failed as expected before
  implementation with missing `citationOptions.disambiguateAddNames` at
  `1 test files, 2341 assertions, 1 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2350 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-citation-csl-add-names-disambiguation-handoff.php --self-test`
  passed.
- PHP lint passed for changed PHP files.
- Lane JSON validation passed.
- `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1580 -> 1581`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2001 -> 2002`.
- `mappedCitationCslCoreCases`: `12 -> 13`.
- Focused Citation/CSL assertion count: `2340 -> 2350`.

## Dependency Closure

No new support component is required. The slice reuses existing bounded native
CSL XML parsing, citation annotation, name rendering, Markdown reading, and
WordPress block writing.

## Non-Overlap

This slice avoids the latest math/TeX dot/relation alias work and the prior CSL
year-suffix, given-name, subsequent-et-al, et-al-use-last, label
disambiguation, and empty `cs:else` validation slices. It adds the missing
`disambiguate-add-names` citation option and renderer handoff only.

## Follow-Up

Useful next Citation/CSL work would be a separate bounded case for interaction
ordering when `disambiguate-add-names`, `disambiguate-add-givenname`, and
`disambiguate-add-year-suffix` are all enabled together, or a note-style
disambiguation context case. Those were intentionally not expanded in this
micro-slice.
