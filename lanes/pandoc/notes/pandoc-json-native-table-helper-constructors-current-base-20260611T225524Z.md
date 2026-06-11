# Pandoc JSON/native table helper constructor wrappers

Slice: `plib-yrhxa` (`20260611T225524Z`)
Base: `ba99e7070b`

## Scope

`PandocJsonWriter` and `NativeWriter` now preserve source-derived table helper
constructor wrappers when rebuilding edited shared AST tables:

- `TableHead`
- `TableBody`
- `TableFoot`
- `Row`
- `Cell`

When the original helper native payload still matches generated content, the
writer reuses it. When table/body attributes or helper contents changed, the
writer keeps the source constructor wrapper and regenerates fresh `c` content
from current shared AST fields. Manually constructed tables without constructor
provenance keep the existing tuple output shape.

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed: 1 file, 1185 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 files, 66963 assertions, 0 failures.

## Accounting

- `lane-status.json` `phpPass`: `3138 -> 3139`.
- Added `mappedJsonNativeTableHelperConstructorWriterCases: 1`.
- Added `jsonNativeTableHelperConstructorWriterAssertions: 28`.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
