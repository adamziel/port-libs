# Pandoc JSON/native caption maybe constructors

Bead: plib-p89lx
Base: origin/main 5cdf8aebd894fa65cbf4c20899c561c7c7aca292
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
- Focused `PandocJsonNativeAstTest.php`: 1 test file, 1775 assertions, 0 failures.
- Focused `NativeReaderTest.php`: 1 test file, 336 assertions, 0 failures.
- Full `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 73152 assertions, 0 failures.

## Accounting

- `phpPass`: 3267 -> 3268.
- `upstream.mapped`: 3245 -> 3246.
- `mappedJsonNativeCaptionMaybeConstructorCases`: 1.
- `jsonNativeCaptionMaybeConstructorAssertions`: 47.

No Pandoc, JSON filters, Cabal/Haskell runners, Node tooling, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
