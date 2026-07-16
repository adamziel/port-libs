# Consolidate Final Numbered JSON Table Dynamic

This slice consolidates collision-free numbered private helper names in
`SQLiteJsonTablePlan` into stable descriptive helper names while preserving
observable JSON table behavior, generated array keys, dependency strings,
opcodes, action labels, and numbered proof names.

The consolidation is intentionally private-method-only. Existing numbered
receipt keys such as `next127ReplanReasons`, dependency strings such as
`sqlite-json-table-lateral-rowid-hidden-current-source-next105`, and opcode
strings such as `OP_JsonTableGeneratedPathRowidYieldGuardDeliverNext224` remain
unchanged for dependent tests and handoff evidence.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTable*Test.php lanes/libsqlite/tests/SQLiteJsonEachIndexedRegressionTest.php
git diff --check -- lanes/libsqlite
```

Focused result:

```text
305 test files, 20306 assertions, 0 failures
```

Dependency closure: no new support component is needed; this reuses the
existing JSON table planner/cursor support and only removes private numbered
helper names where the canonical unsuffixed name has no collision.

Non-overlap: this avoids root-gate behavior changes, dashboard/progress edits,
JSON table observable metadata changes, root publication files, and unrelated
suite-evidence/window root-gate work.
