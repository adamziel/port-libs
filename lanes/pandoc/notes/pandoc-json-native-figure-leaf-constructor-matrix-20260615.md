# Pandoc JSON/native figure leaf constructor matrix

Slice: `plib-wouya`

Area: Pandoc JSON/native AST constructor completeness.

Implemented one bounded constructor-matrix coverage case for figure-local leaf
block constructors. The JSON/native matrix now verifies that `CodeBlock` and
HTML `RawBlock` constructors inside a `Figure` child block list exact-round-trip
through `PandocJsonReader`, `NativeReader`, `PandocJsonWriter`, and
`NativeWriter`.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

Verification:

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 4562 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 86597 assertions, 0 failures`

Accounting:

- rebased current main: `1c04e5385`
- `phpPass`: `3671 -> 3672`
- `phpFail`: `0`
- `upstream.mapped`: `3705 -> 3706`
- `mappedJsonNativeConstructorMatrixCases`: `13 -> 14`
- `jsonNativeConstructorMatrixAssertions`: `232 -> 246`
