# Pandoc JSON/native NativeWriter core block constructors

Slice: `plib-mmcph`

Area: Pandoc JSON/native AST constructor completeness.

Implemented one bounded constructor-matrix coverage case for NativeWriter core
block constructor output. The focused JSON/native test now verifies that
`BlockQuote`, `BulletList`, `OrderedList`, `LineBlock`, `CodeBlock`,
`RawBlock`, `Div`, `HorizontalRule`, and `Null` constructors emitted by
`NativeWriter` are accepted by both `PandocJsonReader` and `NativeReader`.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

Verification:

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 4411 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 86369 assertions, 0 failures`

Accounting:

- rebased current main: `4ddd4ff438`
- `phpPass`: `3665 -> 3666`
- `phpFail`: `0`
- `upstream.mapped`: `3702 -> 3703`
- `mappedJsonNativeConstructorMatrixCases`: `12 -> 13`
- `jsonNativeConstructorMatrixAssertions`: `168 -> 193`
- `mappedJsonNativeConstructorCompletenessCases`: `34 -> 35`
- `jsonNativeConstructorCompletenessAssertions`: `575 -> 600`
- `mappedJsonNativeNativeWriterCoreBlockConstructorCases`: `1`
- `jsonNativeNativeWriterCoreBlockConstructorAssertions`: `25`
