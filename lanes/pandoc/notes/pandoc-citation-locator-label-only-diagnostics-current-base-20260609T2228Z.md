# Pandoc Citation Locator Label-Only Diagnostics Current Base

Slice: `pandoc-citation-locator-label-only-diagnostics-current-base-20260609T2228Z`

Base: `e2e9b323`

## Source Truth

- This bounded native PHP slice extends Citation/CSL locator diagnostics for
  explicit AST citations that carry `locatorLabel` without `locatorValue`.
- Such citations do not render a locator value, but the label metadata is now
  preserved in review diagnostics as `citation-locator-label-without-value`
  instead of being silently dropped.
- Rendered Citation/CSL and WordPress output remain unchanged; the diagnostic
  is review metadata only.
- No Pandoc, citeproc, BibTeX, Biber, Cabal build, Haskell runner, browser
  renderer, online service, live provider test, or live-service provider test
  was executed.

## Implementation

- `CitationCslProcessor` now emits a warning diagnostic for label-only locator
  metadata, preserving the normalized locator label and an empty locator value.
- `CitationCslProcessorTest.php` adds a focused PASS case for label-only
  locator diagnostics and verifies rendered citation output remains locator-free.
- `wordpress-citation-csl-locator-diagnostics-handoff.php` now self-tests the
  label-only diagnostic alongside unlabeled fallback, unsupported labels, and
  explicit-value page-default diagnostics.
- `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` account for one
  additional native Citation/CSL diagnostic case.

## Verification

```text
php -l lanes/pandoc/src/CitationCslProcessor.php
No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php
php -l lanes/pandoc/examples/wordpress-citation-csl-locator-diagnostics-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-citation-csl-locator-diagnostics-handoff.php
```

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 4148 assertions, 0 failures
```

```text
php lanes/pandoc/examples/wordpress-citation-csl-locator-diagnostics-handoff.php --self-test
wordpress-citation-csl-locator-diagnostics-handoff self-test passed
```

```text
php tools/run-tests.php lanes/pandoc/tests
42 test files, 57674 assertions, 0 failures
```

## Status Delta

- `lanes/pandoc/lane-status.json` `suiteProgress`: `760 -> 761`.
- `lanes/pandoc/lane-status.json` `phpPass`: `2857 -> 2858`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` benchmark mapped denominator:
  `3063 -> 3064`.
- Focused `CitationCslProcessorTest.php`: `4138 -> 4148` assertions.

## Non-Overlap And Follow-Up

This slice only covers explicit locator labels with missing values. It does not
change locator parsing, rendered CSL locator output, localized locator terms,
unsupported locator handling, citation sorting/collapsing, BibTeX/BibLaTeX
metadata, or full citeproc parity.
