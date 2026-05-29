# trigger-recursive-view-returning-current-source-done-gate

Status: focused PHP behavior growth for recursive `INSTEAD OF` view trigger `RETURNING` current-source handoff.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext194Plan`. It builds on the accepted next190 resume-source validation and adds the next executor gate: queued next-source recursive view `RETURNING` rows become visible only after the current source has yielded `SQLITE_DONE`, the current source cookie still matches the prepared statement, and the current step epoch matches the drained row stream. `SQLITE_ROW`/`SQLITE_BUSY`, stale source-cookie, stale step-epoch, and stale resume-source paths keep current rows visible while holding next-source rows with explicit block reasons.

WordPress path: `wordpress-trigger-recursive-view-returning-current-source-done-gate.php` models a copied `wp_options` import through a recursive view trigger. It proves plugin-created child rows from the current source are fully drained before the next import source can expose `RETURNING` rows.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceDoneGateTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 86 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-done-gate.php
wordpress-trigger-recursive-view-returning-current-source-done-gate self-test passed
```

Dashboard delta: `phpPass` +86 from the focused PASS-line count. Mapped upstream coverage is unchanged; this is additive current-source PHP behavior over the existing trigger recursive view RETURNING surface.

Dependency closure: no new support component is needed. The slice reuses lane-local recursive view trigger `RETURNING` resume rows and adds current-source `SQLITE_DONE`/source-cookie gating.

Non-overlap: extends accepted next190 resume-source validation with final current-source result-code, source-cookie, and step-epoch gating. It avoids accepted next190 resume-token behavior, next187 drain-ticket matching, next184 checkpoint admission, trigger recursive/view RETURNING next190 accepted coverage, row-value RETURNING, WAL, pager, B-tree, JSON, PRAGMA, encoding, and suite-runner surfaces.

Next task: wire this done/cookie gate into broader native prepared-statement stepping when the recursive view trigger executor owns real cursor advancement instead of bounded row arrays.
