# trigger-recursive-view-upsert-current-source-next237

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
UPSERT current-source admission.

This slice adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext237Plan`. It
builds after the accepted next234 conflict-key receipt fence and adds a narrower
current-source guard: next-source `RETURNING` rows stay held until each current
recursive view UPSERT row has a sealed conflict action (`insert`,
`insert-recursive`, `do-update`, or `do-nothing`) for the current view and
trigger source.

Application path:
`application-trigger-recursive-view-upsert-current-source-next237.php` models a
copied `wp_options` import view where current recursive child UPSERT actions
publish before staged `home` and `next_plugin` rows can become visible.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext237Test.php`
  - Result: `1 test files, 96 assertions, 0 failures` with 96 PASS lines.
- `php lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next237.php`
  - Result: `application-trigger-recursive-view-upsert-current-source-next237 self-test passed`.
- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext237Plan.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext237Test.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next237.php`
  - Result: no syntax errors.
- `git diff --check -- lanes/libsqlite`
  - Result: passed.

Dashboard delta: update `phpPass` by the focused PASS-line delta from the new
test file only (`+96`, from `117718` to `117814`).
`benchmarkDenominator.mapped` is unchanged at `640 / 1589`; this extends
already mapped trigger/view/UPSERT current-source behavior and does not hydrate
a new upstream Tcl inventory unit.

Dependency closure: no new support component is needed. The slice reuses
lane-local recursive view trigger, current-source close, UPSERT receipt, and
RETURNING row-array planning.

Non-overlap: avoids accepted next234 conflict-key receipt admission, recursive
view RETURNING close/epoch/ticket surfaces, trigger RETURNING conflict,
row-value savepoints, schema reparse, WAL/VFS, JSON, planner, encoding, and
B-tree clusters. The narrower behavior is the post-next234 conflict-action
seal before next-source UPSERT rows publish.
