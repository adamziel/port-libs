# Trigger RETURNING UPSERT View Current Source Next149

Status: focused PHP behavior growth for `INSTEAD OF` view-trigger UPSERT
`RETURNING` current-source draining when the next schema source rewrites the
view trigger body.

This slice adds `SQLiteTriggerReturningUpsertViewCurrentSourceNextPlan`.
It models copied Application `wp_options` import rows inserted through a view
trigger. The current trigger source is drained first and its `RETURNING` rows
are captured with the current trigger-source token. A changed next trigger
source is reported separately as attempted-only unless the caller explicitly
admits it. `RAISE(IGNORE)`-style skipped rows suppress `RETURNING` rows without
rolling back the current source.

Application path:
`application-trigger-returning-upsert-view-current-source-next.php` previews a
plugin migration that rewrites the import view trigger between current and next
schema cookies. The smoke proves the current trigger source remains visible
until reset while next-source `RETURNING` rows stay attempted-only.

Focused verification:

```sh
$ php -l lanes/libsqlite/src/SQLiteTriggerReturningUpsertViewCurrentSourceNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerReturningUpsertViewCurrentSourceNextPlan.php
$ php -l lanes/libsqlite/tests/SQLiteTriggerReturningUpsertViewCurrentSourceNextTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerReturningUpsertViewCurrentSourceNextTest.php
$ php -l lanes/libsqlite/examples/application-trigger-returning-upsert-view-current-source-next.php
No syntax errors detected in lanes/libsqlite/examples/application-trigger-returning-upsert-view-current-source-next.php
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerReturningUpsertViewCurrentSourceNextTest.php
1 test files, 69 assertions, 0 failures
$ php lanes/libsqlite/examples/application-trigger-returning-upsert-view-current-source-next.php --self-test
application-trigger-returning-upsert-view-current-source-next self-test passed
```

Dashboard delta: `phpPass` should increase by 69 focused PASS lines
(`66428 -> 66497`) when accepted. `benchmarkDenominator.mapped` remains `606 /
1589`; this is behavior-backed PHP coverage, not a newly mapped upstream Tcl
inventory unit.

Non-overlap: avoids accepted next144 trigger UPSERT RETURNING view
release/retain source mapping, next138 trigger `RAISE(IGNORE)` UPSERT
savepoint behavior, next128/next136 view-trigger RETURNING savepoint behavior,
next125/next131 schema view/trigger reparse diagnostics, next104 attach/temp
trigger source reprepare, and accepted WAL/VFS/B-tree/JSON/encoding/SELECT
clusters. The new surface is specifically view-trigger source identity and
attempted next-source `RETURNING` isolation at the current-source boundary.

Dependency closure: no new support component is needed. The slice reuses
lane-local row-array UPSERT, `RETURNING` projection, view mapping, and trigger
source metadata primitives.

Next task: wire this current-source trigger identity into a broader native
prepared statement executor so reset/reprepare can promote attempted next-source
rows only after the current trigger program has fully drained.
