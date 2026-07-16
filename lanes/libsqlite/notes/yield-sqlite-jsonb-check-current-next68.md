# yield-sqlite-jsonb-check-current-next68

Status: focused PHP behavior growth for JSONB CHECK current/next admission.

This slice extends `SQLiteJsonbCheckCurrentNextPlan` for SQLite CHECK semantics
where a CHECK expression only rejects false/zero results; NULL comparison
results are admitted. It also adds top-level OR disjunction evaluation inside
CHECK terms, preserving child term diagnostics for current/next row previews.

Application smoke: `examples/application-jsonb-check-current-next68.php` preflights
copied `wp_options` plugin-setting JSONB rows with optional fields such as
description, channel, and priority before storage admission.

Focused evidence:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext68Test.php
Focused test run: 1 selected test files (root lock skipped)
48 PASS lines
1 test files, 106 assertions, 0 failures

$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext64Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext68Test.php
Focused test run: 2 selected test files (root lock skipped)
92 PASS lines
2 test files, 170 assertions, 0 failures
```

Non-overlap: this avoids queued/accepted JSONB delete/cascade/check generated
index maintenance, JSON table hidden/visible/source/cursor constraints, VFS/WAL
savepoint/checkpoint/rollback clusters, B-tree freelist/pointer-map clusters,
SELECT SQL text/subquery/group/order clusters, PRAGMA integrity surfaces, and
suite evidence/status-only handoffs. The new surface is optional JSONB CHECK
admission semantics for NULL results and OR disjunctions in current/next row
preflight.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP JSONB codec, JSON extraction/inspection helpers, JSON
mutation helpers, and lane-local TestRunner.
