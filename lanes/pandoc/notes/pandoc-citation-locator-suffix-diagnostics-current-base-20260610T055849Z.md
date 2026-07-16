# Pandoc Citation Locator Suffix Diagnostics Current Base

Slice: `pandoc-citation-locator-suffix-diagnostics-current-base-20260610T055849Z`

Base: `6b183a65e`

## Source Truth

- This bounded native PHP slice extends Citation/CSL locator diagnostics for
  Pandoc JSON citations imported with `citationSuffix` values.
- When explicit `locator` and `locatorValue` metadata are absent,
  `CitationCslProcessor` now treats the imported suffix text as the fallback
  locator candidate for CSL rendering and review diagnostics.
- The diagnostic packet preserves suffix-derived raw locator text with
  `citation-locator-suffix-inferred`, while keeping the existing unsupported
  label and unlabeled page fallback diagnostics.
- No Pandoc, citeproc, BibTeX, Biber, Cabal build, Haskell runner, browser
  renderer, online service, live provider test, or live-service provider test
  was executed.

## Implementation

- `CitationCslProcessor` now checks citation `suffix` metadata after explicit
  locator values and raw locator text have been ruled out.
- Suffix-derived locators flow through the existing locator inference path, so
  known labels such as `p. 7` normalize to page locators while unknown labels
  such as `plate A` retain the existing page fallback review warning.
- `CitationCslProcessorTest.php` adds a Pandoc JSON reader handoff case that
  verifies diagnostics, normalized citation nodes, rendered CSL output, and
  WordPress block output.
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
1 test files, 4180 assertions, 0 failures
```

```text
php lanes/pandoc/examples/wordpress-citation-csl-locator-diagnostics-handoff.php --self-test
wordpress-citation-csl-locator-diagnostics-handoff self-test passed
```

```text
php tools/run-tests.php lanes/pandoc/tests
43 test files, 58857 assertions, 0 failures
```

## Status Delta

- `lanes/pandoc/lane-status.json` `suiteProgress`: `837 -> 838`.
- `lanes/pandoc/lane-status.json` `phpPass`: `2934 -> 2935`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` benchmark mapped denominator:
  `3112 -> 3113`.
- Focused `CitationCslProcessorTest.php`: `4172 -> 4180` assertions.

## Non-Overlap And Follow-Up

This slice only covers Pandoc JSON `citationSuffix` locator fallback behavior.
It does not change explicit `locatorValue` handling, direct Markdown locator
parsing, BibTeX/BibLaTeX metadata, citation sorting/collapsing, localized CSL
terms, or full citeproc parity.
