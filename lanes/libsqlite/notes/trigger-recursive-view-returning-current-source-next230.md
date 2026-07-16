# trigger-recursive-view-returning-current-source-next230

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
`RETURNING` rows at the current/next source boundary.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext230Plan`.
It builds on accepted next226 following-current seal behavior and adds a
current-source epoch receipt fence before subsequent next-source `RETURNING`
rows can publish. Current/following rows remain visible while subsequent next
rows are held for missing, unexpected, reversed, stale epoch, or stale cursor
receipts.

Application path: `application-trigger-recursive-view-returning-current-source-next230.php`
models a copied `wp_options` import view whose recursive trigger drains and
seals current rows before a later plugin/source epoch may expose cron and
widget option rows from the subsequent next source.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext230Test.php`
- Result: `1 test files, 75 assertions, 0 failures` with 75 PASS lines.
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next230.php`
- Result: `application-trigger-recursive-view-returning-current-source-next230 self-test passed`.

Dashboard delta: update `phpPass` by the focused PASS-line delta verified for
this test file (`+75`, from `112201` to `112276`). `benchmarkDenominator.mapped`
is unchanged at `631 / 1589`; this is current-source PHP behavior over already
mapped trigger/view/RETURNING inventory, not a newly hydrated upstream row.

Dependency closure: no new support component is needed. The slice reuses
lane-local recursive view trigger, RETURNING, current-source drain/yield,
provenance, reset, and following-current seal primitives.

Non-overlap: avoids accepted trigger recursive view RETURNING next157-next226
surfaces, especially next226 following-current seal, next222 source ticket,
next219 reset, and next212 yield receipts. It also avoids row-value RETURNING
savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse,
WAL/VFS, JSON table, planner, encoding, and B-tree clusters. The narrower
behavior is current-source epoch admission after the accepted next226
following-current seal.
