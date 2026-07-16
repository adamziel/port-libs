# Pandoc JSON/native table helper constructors

Slice: `plib-dlwpy`
Base: current main `2e6e6bc9e`

Implemented one bounded constructor-completeness case for regenerated table
helper wrappers. `PandocJsonWriter` and `NativeWriter` now emit tagged
`TableHead`, `TableBody`, `TableFoot`, `Row`, and `Cell` helper constructors
for generated or rebuilt table sections while still reusing unchanged native
helper sidecars when their payloads match.

The focused JSON/native tests now cover canonical regenerated table helper tags
and still verify legacy table payload preservation. Neighboring delimited-text
and native-writer table tests unwrap tagged-or-legacy helper payloads before
checking row-head, span, foot, and empty-head contents.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

Verification:

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php -l lanes/pandoc/tests/DelimitedTextReaderTest.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 4735 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/DelimitedTextReaderTest.php lanes/pandoc/tests/NativeReaderTest.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `3 test files, 5450 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 86918 assertions, 0 failures`

Accounting:

- `phpPass`: `3681 -> 3682`
- `phpFail`: `0`
- `mappedJsonNativeConstructorCompletenessCases`: `38 -> 39`
- `jsonNativeConstructorCompletenessAssertions`: `799 -> 806`
