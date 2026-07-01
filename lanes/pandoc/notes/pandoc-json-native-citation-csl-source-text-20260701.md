# Pandoc JSON/native citation CSL source text

Bead: `plib-lglho`
Date: 2026-07-01 UTC

## Change

- Preserves Pandoc JSON/native `Cite` source text when CSL fallback rendering
  encounters a missing citation item with a prefix.
- Prevents prefixed suppress-author missing citations such as
  `compare -@missing-source` from rendering as `compare compare -@missing-source`
  during CSL and WordPress handoff.
- Keeps the existing manually authored missing-citation fallback behavior, so a
  citation with only `id`, `text`, and `prefix` still composes the prefix when
  the source text does not already contain it.

## Parity

This is a bounded JSON/native AST constructor-completeness and CSL handoff fix.
It does not change direct-format parity accounting or invoke external citation
processors, Pandoc, office suites, TeX/browser engines, Node tooling, zip/unzip
tools, validators, online services, or live providers.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/PandocJsonCitationCslSourceTextTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonCitationCslSourceTextTest.php lanes/pandoc/tests/NativeReaderTextualCitationConstructorTest.php lanes/pandoc/tests/JsonReaderHelperConstructorCompatibilityTest.php`
  - `3 test files, 80 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 6,187 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - baseline-red with `1 test files, 6,087 assertions, 5 failures`; the targeted
    `round trips pandoc json cite inlines with csl metadata for wordpress handoff`
    case now passes, and the remaining failures are the existing WordPress
    raw/attribute/figure caption baselines outside this slice.
