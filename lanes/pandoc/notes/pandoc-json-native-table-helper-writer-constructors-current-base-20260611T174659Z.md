# Pandoc JSON/native table helper writer constructors

Bead: `plib-ahsgz`
Base: `7f3338836`
Scope: JSON/native AST constructor completeness.

## Change

`PandocJsonWriter` and `NativeWriter` now preserve source-tagged Pandoc table helper constructors when regenerating edited table payloads:

- `TableHead`
- `TableBody`
- `TableFoot`
- `Row`
- `Cell`
- `RowHeadColumns`

Tuple-shaped source helpers remain tuple-shaped. Tagged helper payloads are reused when generated content is unchanged, and rebuilt under the same constructor wrapper when table-level edits prevent whole-table native payload reuse.

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test file, 1115 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 66563 assertions, 0 failures`

Metric:

- `phpPass`: `3130 -> 3131`
- `phpFail`: `0`
- `mappedJsonNativeTaggedTableHelperConstructorCases`: `1`
- `jsonNativeTaggedTableHelperConstructorAssertions`: `44`

No Pandoc executable, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
