# Pandoc JSON/native wrapped integer table helpers

Slice: `plib-z7jon`

Area: Pandoc JSON/native AST constructor completeness.

Implemented one bounded constructor-completeness case for table helper
constructors that carry single-wrapped integer payloads. The JSON/native test
now verifies that `RowHeadColumns`, `RowSpan`, and `ColSpan` payloads such as
`{"t":"RowSpan","c":[2]}` keep their reviewer sidecars when table, body, row,
and cell wrappers are rebuilt through both `PandocJsonWriter` and
`NativeWriter`. The case also verifies JSON and native round trips recover the
same row-head, row-span, and column-span values.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

Verification:

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 3903 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 85068 assertions, 0 failures`

Accounting:

- rebased current main: `cf59a20a94`
- `phpPass`: `3624 -> 3625`
- `phpFail`: `0`
- `upstream.mapped`: `3636 -> 3637`
- `mappedJsonNativeConstructorCompletenessCases`: `11 -> 12`
- `jsonNativeConstructorCompletenessAssertions`: `182 -> 238`
