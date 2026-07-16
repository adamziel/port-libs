# Pandoc JSON/native table-local leaf block constructor matrix

Slice: `plib-24ffr`

Area: Pandoc JSON/native AST constructor completeness.

Implemented one bounded constructor-matrix coverage case for table-local leaf
block constructors. The JSON/native matrix now verifies that `CodeBlock` and
`RawBlock` constructors inside `Caption` and `Cell` block lists exact-round-trip
through `PandocJsonReader`, `NativeReader`, `PandocJsonWriter`, and
`NativeWriter`.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

Verification:

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 4291 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 85823 assertions, 0 failures`

Accounting:

- rebased current main: `c3a11b35c`
- `phpPass`: `3643 -> 3644`
- `phpFail`: `0`
- `upstream.mapped`: `3678 -> 3679`
- `mappedJsonNativeConstructorMatrixCases`: `11 -> 12`
- `jsonNativeConstructorMatrixAssertions`: `154 -> 168`
