# Source-Neutral Compound Window Defaults Dynamic

Slice: `source-neutral-src-compound-window-defaults-dynamic-20260602T001101Z-0`
Base accepted HEAD: `df6aab6c7b87e548fe655763cf42a9438f111f94`

## Change

- Hardened `SQLiteSourceNeutralCompoundWindowDefaultsDynamicTest` so its
  compound/window source scan is derived dynamically from current
  `SQLiteCompound*.php`, `SQLite*Window*.php`, and the recursive window
  materializer source instead of a short hand-maintained list.
- Added an inventory assertion covering the current broad compound/window
  source family, including the recursive-limit plan, row-value window plan, and
  VDBE window cursor, without freezing future neutral file additions to an
  exact count.
- Added the same dynamic compound/window source scan to the central
  `SQLiteNoDomainSpecificApiTest` gate so future production files in this
  family are checked for legacy option/table/load-policy source terms.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralCompoundWindowDefaultsDynamicTest.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralCompoundWindowDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `2 test files, 29 assertions, 0 failures`.

## Status Delta

Source-neutral guard hardening only. No `phpPass`, mapped coverage, or broad
release-parity counter movement is claimed.

## Dependency Closure

No new support component is needed. This reuses the existing native compound
SELECT, recursive CTE, window, VDBE window cursor, row-value window, and
source-neutral no-domain guard coverage.

## Non-Overlap

This slice does not add upstream corpus rows, runner metadata, compatibility
aliases, dashboard/root edits, or new domain-shaped wrappers. It extends the
accepted compound/HAVING/window source-neutral cleanup from a static file list
to a dynamic source-family guard.

Root harness: not run - isolated micro-slice.
