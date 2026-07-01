# Pandoc JSON Native Table Row Group Sidecars

Slice: `plib-khvn0`

## Summary

`PandocJsonNativeAstTest` now has focused coverage for table row-group native
payload preservation after safe table rebuilds:

- `TableHead` row payload sidecars are recorded and reused when the table and
  section wrappers are regenerated.
- `TableBody` preserves `RowHeadColumns`, intermediate head-row payloads, and
  body-row payloads independently.
- Edits to a head row, intermediate body head row, or body row invalidate only
  that stale row-group sidecar while unchanged neighboring groups keep their
  source payloads.

This is a native PHP AST read/write regression only; no Pandoc binary, Haskell,
Node, browser, validator, online service, or external conversion tool was used.

## Validation

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - New row-group sidecar regression: pass.
  - Current file result: `1 test files, 6251 assertions, 6 failures`.
  - The six failures are pre-existing WordPress/raw/citation handoff assertions
    outside this table-native coverage.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Full lane result: `534 test files, 142474 assertions, 8912 failures`.
  - The new row-group sidecar regression passed; remaining failures are the
    current broad Pandoc lane baseline outside this table-native test-only
    slice.
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check`
