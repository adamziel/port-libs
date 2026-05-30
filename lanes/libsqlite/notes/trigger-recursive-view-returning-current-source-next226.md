# trigger-recursive-view-returning-current-source-next226

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
`RETURNING` source admission at the current-source to next-source boundary.

Behavior: `SQLiteTriggerRecursiveViewReturningCurrentSourceNext226Plan` builds
on the accepted next219 next-source reset gate. After next219 exposes a
following current-source `RETURNING` stream, this slice requires ordered seal
receipts for those following-current rows before a subsequent next-source view
trigger generation can become visible. Missing, unexpected, out-of-order,
cursor-mismatched, and token-mismatched seals fence the subsequent next-source
rows while retaining the already visible current/following rows.

Application path: copied `wp_options` recursive import views can drain current,
next, and following-current `RETURNING` rows, then safely admit a subsequent
next-source import for options such as `cron` and `widget_block` only after the
following-current stream is sealed.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext226Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 94 assertions, 0 failures
```

Smoke:

```sh
php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next226.php
```

Result:

```text
application-trigger-recursive-view-returning-current-source-next226 self-test passed
```

Expected dashboard movement: `phpPass` +94 (`108262` to `108356`) once this
lane is accepted. Mapped upstream coverage remains `625 / 1589`; this is
current-source PHP behavior over already mapped trigger, recursive view, and
`RETURNING` inventory, not a newly hydrated upstream row.

Non-overlap: avoids next219 next-source reset admission, next217 provenance,
next212 yield receipts, next190 resume-source validation, row-value RETURNING
savepoints, DML RETURNING conflicts, schema reparse, WAL/VFS, JSON table,
planner, encoding, B-tree, and suite-runner clusters.

Dependency closure: no new support component is needed. The slice reuses
lane-local recursive view trigger `RETURNING` planners and adds a bounded
following-current seal admission gate.

Next task: wire this following-current seal into the parser-level trigger
executor when native view-trigger bytecode owns chained current/next source
cursor suspension.
