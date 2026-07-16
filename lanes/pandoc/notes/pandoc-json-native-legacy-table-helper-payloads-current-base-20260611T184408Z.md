# Pandoc JSON/Native Legacy Table Helper Payloads

Bead: `plib-s9pcs`
Base: `7b44c42fe`

## Scope

This slice keeps legacy five-slot Pandoc `Table` constructor handoff
constructor-complete for JSON/native AST review.

`PandocJsonReader` and `NativeReader` now preserve legacy table column helper
provenance while still exposing normalized shared AST fields:
- raw alignment enum payloads as `alignmentNatives`
- normalized width helper constructors as `columnWidthConstructors`
- raw legacy width payloads as `columnWidthNatives`

The existing normalized `alignments`, `widths`, and native legacy-table
round-trip behavior stay intact. `PandocJsonWriter` continues to emit canonical
current Table JSON from normalized fields.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed: 1 test file, 1036 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 65260 assertions, 0 failures.

## Accounting

- Adds 1 focused JSON/native legacy table helper payload PASS case.
- Adds 32 focused assertions.
- Moves Pandoc lane `phpPass` from 3096 to 3097 on current main `7b44c42fe`.
- Keeps `phpFail` at 0.

No Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external
validators, online services, live provider tests, or live-service provider tests
were invoked.
