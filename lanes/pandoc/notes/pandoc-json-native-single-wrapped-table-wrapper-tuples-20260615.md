# Pandoc JSON/native single-wrapped table wrapper tuples

Slice: `plib-92q5t`

Area: Pandoc JSON/native AST constructor completeness.

Implemented one bounded constructor-completeness case for single-wrapped table
wrapper tuple payloads. `PandocJsonReader` and `NativeReader` now accept
`TableHead`, `TableBody`, `Row`, and `Cell` constructors whose `c` field wraps
the expected tuple in one extra list. `PandocJsonWriter` and `NativeWriter` now
reuse those unchanged wrapper payloads when regenerating an edited outer table
shell, so reviewer sidecars on table sections, rows, and cells survive until
their own payload boundary changes.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

Verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 4690 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 86816 assertions, 0 failures`

Accounting:

- rebased current main: `018189c5fd`
- `phpPass`: `3677 -> 3678`
- `phpFail`: `0`
- upstream mapped cases: `3709 -> 3710`
- `mappedJsonNativeConstructorCompletenessCases`: `37 -> 38`
- `jsonNativeConstructorCompletenessAssertions`: `729 -> 799`
