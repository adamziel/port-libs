# Pandoc JSON/native Note leaf constructor matrix

Slice: `plib-8bcm8`

Area: Pandoc JSON/native AST constructor completeness.

Implemented one bounded constructor-matrix coverage case for Note-local leaf
block constructors on top of main's tagged table helper emission, inline helper
variant matrix, fallback constructor matrix, EPUB manifest dependency
inventory, document constructor matrix, and table wrapper constructor slices.
The JSON/native matrix now verifies that `CodeBlock` and HTML `RawBlock`
constructors inside a `Note` block list exact-round-trip through
`PandocJsonReader`, `NativeReader`, `PandocJsonWriter`, and `NativeWriter`.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

Verification:

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 5166 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 87789 assertions, 0 failures`

Accounting:

- rebased current main: `7d8fbf700`
- `phpPass`: `3706 -> 3707`
- `phpFail`: `0`
- `upstream.mapped`: `3730 -> 3731`
- `mappedJsonNativeConstructorMatrixCases`: `17 -> 18` in lane status and manifest top level; `16 -> 17` in the upstream manifest
- `jsonNativeConstructorMatrixAssertions`: `303 -> 317` in lane status and manifest top level; `278 -> 292` in the upstream manifest
