# Pandoc JSON/native caption maybe constructors

Bead: plib-p89lx
Base: origin/main 02f4cf3c983e5aefab352f15db9eb766c4a486af
Scope: JSON/native AST constructor completeness for generated table and figure captions.

## Slice

Generated `PandocJsonWriter` and `NativeWriter` table/figure captions now emit canonical Pandoc caption helper constructors:

- `Caption`
- `Just`
- `ShortCaption`
- `Nothing`

Unchanged native `Caption` payloads are still reused so reader-preserved tagged input stays stable.

## Evidence

- Conflict-refresh check before expectation repair: `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` showed generated-caption constructor shape mismatches on current main fixtures.
- Syntax checks passed for `PandocJsonWriter.php`, `NativeWriter.php`, `PandocJsonNativeAstTest.php`, and `NativeReaderTest.php`.
- Focused `PandocJsonNativeAstTest.php`: 1 test file, 1865 assertions, 0 failures.
- Focused `NativeReaderTest.php`: 1 test file, 336 assertions, 0 failures.
- Full `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 73310 assertions, 0 failures.

## Accounting

- `phpPass`: 3272 -> 3273.
- `upstream.mapped`: 3246 -> 3247.
- `mappedJsonNativeCaptionMaybeConstructorCases`: 1.
- `jsonNativeCaptionMaybeConstructorAssertions`: 47.

No Pandoc, JSON filters, Cabal/Haskell runners, Node tooling, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
