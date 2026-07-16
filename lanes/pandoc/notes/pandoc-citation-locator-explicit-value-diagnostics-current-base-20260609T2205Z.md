# Pandoc Citation Locator Explicit Value Diagnostics Current Base

Slice: `pandoc-citation-locator-explicit-value-diagnostics-current-base-20260609T2205Z`

Base: `1dd21793`

## Source Truth

- This bounded native PHP slice extends the existing Citation/CSL locator diagnostics coverage to explicit AST `locatorValue` inputs that omit `locatorLabel`.
- These citations default to page locator metadata and emit `citation-locator-explicit-value-defaulted-page` for reviewer handoff.
- Rendered citation output remains unchanged; the diagnostic is review metadata only.
- No Pandoc, citeproc, BibTeX, Biber, Cabal build, Haskell runner, browser renderer, online service, live provider test, or live-service provider test was executed.

## Implementation

- `CitationCslProcessorTest.php` adds a focused PASS case for explicit `locatorValue` diagnostics, asserting the defaulted page label, locator value, diagnostic reason, normalized citation metadata, and rendered cluster output.
- `wordpress-citation-csl-locator-diagnostics-handoff.php` now self-tests the same explicit-value diagnostic branch alongside unlabeled free-text locator fallback and unsupported explicit labels.
- `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` account for one additional native Citation/CSL diagnostic case.

## Verification

```text
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php
php -l lanes/pandoc/examples/wordpress-citation-csl-locator-diagnostics-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-citation-csl-locator-diagnostics-handoff.php
```

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 4138 assertions, 0 failures
```

```text
php lanes/pandoc/examples/wordpress-citation-csl-locator-diagnostics-handoff.php --self-test
wordpress-citation-csl-locator-diagnostics-handoff self-test passed
```

```text
php tools/run-tests.php lanes/pandoc/tests
42 test files, 57515 assertions, 0 failures
```

## Status Delta

- `lanes/pandoc/lane-status.json` `suiteProgress`: `757 -> 758`.
- `lanes/pandoc/lane-status.json` `phpPass`: `2854 -> 2855`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` benchmark mapped denominator: `3060 -> 3061`.
- Focused `CitationCslProcessorTest.php`: `4128 -> 4138` assertions.

## Non-Overlap And Follow-Up

This slice only covers explicit `locatorValue` page-default diagnostics. It does not change locator parsing, rendered CSL locator output, localized locator terms, unsupported locator handling, citation sorting/collapsing, BibTeX/BibLaTeX metadata, or full citeproc parity.

Future bounded Citation/CSL work can cover localized diagnostic labels, richer locator inference, or upstream citeproc parity gaps separately.
