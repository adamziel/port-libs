# Pandoc JSON/native table span edit sidecars

Bead: `plib-ryyog`
Date: 2026-06-14 UTC

Added focused `PandocJsonNativeAstTest` coverage for edited spanning table
cells. The regression fixture reads a table cell with sidecar-bearing `Attr`,
`RowSpan`, and `ColSpan` helpers from both JSON and native payloads, then edits
the shared AST cell id/classes/attributes plus row and column spans.

The JSON and native writers now have explicit coverage proving that edited
spanning cells regenerate canonical `Attr`, `RowSpan`, and `ColSpan` payloads,
drop stale edited-cell wrapper/helper `reviewQueue` and `sourceOrdinal`
sidecars, preserve the neighboring cell's full sidecar-bearing wrapper, and
preserve unchanged `RowHeadColumns`, column alignment, and column-width helper
sidecars.

This slice is recorded as a lane note instead of touching
`lane-status.json` or `UPSTREAM_TEST_MANIFEST.json`, because the prior merge
attempt for this bead failed in those aggregate files. No Pandoc binary, JSON
filters, Cabal/Haskell runners, browser renderer, Node tooling, online service,
live provider test, or external validator was invoked.

## Verification

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 3354 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 82794 assertions, 0 failures`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
