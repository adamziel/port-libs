# Pandoc JSON/native caption maybe constructors

Bead: plib-p89lx
Base: origin/main 298ff12f39b73c4e8c016a7a12becd12e66b7b26
Scope: JSON/native AST constructor completeness for generated table and figure captions.

## Slice

Generated `PandocJsonWriter` and `NativeWriter` table/figure captions now emit canonical Pandoc caption helper constructors:

- `Caption`
- `Just`
- `ShortCaption`
- `Nothing`

Unchanged native `Caption` payloads are still reused so reader-preserved tagged input stays stable.

## Evidence

- Red check before implementation: `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` failed the new generated-caption constructor regression.
- Syntax checks passed for `PandocJsonWriter.php`, `NativeWriter.php`, `PandocJsonNativeAstTest.php`, and `NativeReaderTest.php`.
- Focused `PandocJsonNativeAstTest.php`: 1 test file, 943 assertions, 0 failures.
- Focused `NativeReaderTest.php`: 1 test file, 336 assertions, 0 failures.
- Full `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 64768 assertions, 0 failures.

## Accounting

- `phpPass`: 3087 -> 3088.
- `upstream.mapped`: 3201 -> 3202.
- `mappedJsonNativeCaptionMaybeConstructorCases`: 1.
- `jsonNativeCaptionMaybeConstructorAssertions`: 38.

No Pandoc, JSON filters, Cabal/Haskell runners, Node tooling, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
