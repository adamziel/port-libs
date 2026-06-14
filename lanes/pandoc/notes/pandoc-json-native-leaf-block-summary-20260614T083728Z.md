# Pandoc JSON/native leaf block summary constructors

Bead: `plib-sez1w`
Date: 2026-06-14 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

`PandocJsonReader` and `NativeReader` now include leaf block constructor text
when deriving review summaries from native `Caption` and `Cell` block lists.
This covers `CodeBlock` and raw block variants such as HTML `RawBlock`, while
also carrying existing inline `math`/raw inline text through the same summary
walker.

The shared AST still preserves the original native table payload. Unchanged
source tables round-trip through both `PandocJsonWriter` and `NativeWriter`
without canonicalizing away legal nested constructor shape.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

## Accounting

- `phpPass`: `3496 -> 3497`
- `phpFail`: `0`
- `mappedJsonNativeLeafBlockSummaryCases`: `1`
- `jsonNativeLeafBlockSummaryAssertions`: `12`

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 3258 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 82216 assertions, 0 failures`
