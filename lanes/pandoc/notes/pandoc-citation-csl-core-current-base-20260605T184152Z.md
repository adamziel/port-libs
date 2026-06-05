# Pandoc Citation CSL Core Current Base Subsequent Author Rules

Slice: `pandoc-citation-csl-core-current-base-20260605T184152Z`
Base accepted HEAD: `a8742b82a0b53775ea3a50718efc4d21dc648570`
Date: 2026-06-05 UTC

## Scope

Implemented one bounded Citation/CSL support-library behavior: bibliography
`subsequent-author-substitute-rule` handling now covers the accepted CSL
variants `complete-each`, `partial-each`, and `partial-first`.

Source truth is the CSL 1.0.2 reference-grouping rule text:
https://docs.citationstyles.org/en/v1.0.2/specification.html#reference-grouping

The processor still preserves existing `complete-all` behavior. The new path
tracks the previous bibliography entry's rendered name parts, compares matches
from the first rendered name through the first mismatch, and then rebuilds the
final name list with the style's delimiter and et-al handling intact.

## Changes

- `src/CitationCslProcessor.php`: stores previous bibliography name parts and
  applies `complete-each`, `partial-each`, and `partial-first` substitutions
  before joining rendered bibliography names.
- `tests/CitationCslProcessorTest.php`: adds a focused native PHP case for the
  three rule variants plus WordPress block bibliography output.
- `examples/wordpress-citation-csl-subsequent-author-rule-handoff.php`: adds a
  WordPress smoke for `partial-each` bibliography substitution.
- `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`: record one additional
  mapped native Citation/CSL support case.

## Verification

Baseline before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1356 assertions, 0 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1370 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-citation-csl-subsequent-author-rule-handoff.php --self-test
wordpress-citation-csl-subsequent-author-rule-handoff self-test passed
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1036 -> 1037`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1488 -> 1489`
- `UPSTREAM_TEST_MANIFEST.json` `mappedCitationCslCoreCases`: `10 -> 11`
- Focused `CitationCslProcessorTest.php`: `+1` PASS case and `+14`
  assertions.

## Dependency Closure

No new support component is needed. This reuses native PHP
`CitationCslProcessor`, `CslStyle`, `MarkdownReader`, and
`WordPressBlockWriter` paths.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, external bibliography manager, online sanitizer, or online service was
executed.

The upstream-runner dependency gate remains unchanged: hydrate the pinned
Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal
project/package files and runner suites present before any non-mutating Cabal
plan is marked ready.

## Non-Overlap

This does not repeat accepted Citation/CSL slices for date-part rendering,
title/container short forms, locator/page labels, number forms,
citation-number collapse, position/near-note conditionals, bibliography
display parts, year-suffix disambiguation, citation collapse, et-al
subsequent thresholds, et-al-use-last, or `complete-all` subsequent-author
substitution. It only owns the bounded remaining subsequent-author substitute
rule variants.

## Follow-Up

Keep richer citeproc disambiguation, note-style bibliography and
citation-position interactions, locale-specific name and label terms, full CSL
sorting/grouping edge parity, and upstream Haskell runner parity as separate
bounded slices.
