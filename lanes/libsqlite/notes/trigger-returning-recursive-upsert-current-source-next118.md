# trigger-returning-recursive-upsert-current-source-next118

Status: focused PHP behavior growth for recursive trigger-generated UPSERT
`RETURNING` handoff from a current source batch into the next source batch.

This slice adds `SQLiteTriggerReturningRecursiveUpsertCurrentSourceNextPlan`.
It composes the existing recursive UPSERT/RETURNING executor across a current
batch and a next batch, preserving current-source yield edges and proving that
the next batch starts from the current batch's recursive trigger result rows.
The next source can then update a trigger-generated current row and recurse
again, producing a second `RETURNING` stream without losing statement/trigger
depth evidence.

Application path: `application-trigger-returning-recursive-upsert-current-source-next118.php`
models copied `wp_options` import batches where plugin triggers recursively
upsert child option rows. The current batch returns `plugin_seed` and
`fresh_plugin` recursive rows; the next batch sees those rows as its source,
updates `plugin_seed:child`, and returns the next recursive stream.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerReturningRecursiveUpsertCurrentSourceNext118Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 59 assertions, 0 failures

$ php lanes/libsqlite/examples/application-trigger-returning-recursive-upsert-current-source-next118.php --self-test
application-trigger-returning-recursive-upsert-current-source-next118 self-test passed
```

Dashboard delta: update `phpPass` by the focused PASS-line delta verified for
this test file. `benchmarkDenominator.mapped` is unchanged; this is additional
PHP behavior over already mapped trigger/UPSERT/RETURNING surfaces, not a newly
hydrated upstream Tcl inventory unit.

Non-overlap: avoids accepted DML trigger RETURNING conflict next106, recursive
savepoint UPSERT next27, trigger UPSERT RETURNING recursive next51, trigger/FK
RETURNING UPSERT savepoint next75, view/UPSERT RETURNING, deferred-FK cascade,
WAL/savepoint/VFS, B-tree, JSON table, SELECT SQL, and encoding clusters. The
new surface is the current-source to next-source handoff where recursive
trigger-generated UPSERT rows become the source for the following `RETURNING`
batch.

Dependency closure: no new support component is needed. The slice reuses the
lane-local recursive UPSERT/RETURNING executor and native PHP row-array trigger
machinery; no ext/sqlite, upstream binary, or live-service provider is used.

Next task: wire these current-source/next-source yield edges into a broader
parser-level INSERT/UPSERT executor when multi-statement trigger programs own
native source handoff directly.
