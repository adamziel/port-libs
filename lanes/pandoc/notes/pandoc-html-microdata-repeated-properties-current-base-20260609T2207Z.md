# Pandoc HTML Microdata Repeated Properties Slice

Micro-slice: `pandoc-html-microdata-repeated-properties-current-base-20260609T2207Z`

## Scope

`Html5DomFragment` now records repeated scoped microdata property names as
bounded, inert reviewer metadata:

- `data-pandoc-microdata-repeated-properties`
- `data-pandoc-microdata-repeated-property-count`

The summary is derived from the existing sanitized microdata property walk. It
detects repeated direct property names inside an `itemscope` and also detects
repetitions introduced when resolved external `itemref` property summaries are
merged into the scoped item metadata.

## Verification

Syntax checks:

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`

Focused checks:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: 1 file, 2559 assertions, 0 failures

Full Pandoc PHP gate:

- `php tools/run-tests.php lanes/pandoc/tests`
- Result after rebasing on `origin/main`: 42 files, 58236 assertions, 0 failures
- Rebased lane counters: `phpPass` 2896 -> 2897 and `suiteProgress` 799 -> 800

## Non-Overlap

This does not implement JSON-LD export, RDFa graph construction, Schema.org
validation, recursive itemref expansion, browser DOM execution, Pandoc
execution, or external validation. It only records repeated property-name
metadata for already-normalized native HTML reader fragments.
