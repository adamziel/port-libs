# attach-wal-temp-current-next

Implemented bounded ATTACH main/temp/attached transaction routing in `SQLiteAttachWalTempCurrentNextPlan`.

Behavior covered:

- Unqualified writes resolve through SQLite search order: `temp`, `main`, then attached schemas.
- Persistent WAL schemas append per-WAL-file frame indexes and increment page/change-counter state once per dirty schema.
- `temp` uses rollback-journal delete-on-commit by default, or memory-journal discard when `temp_store=memory` is requested.
- Read-only attached schemas reject writes before a transaction plan is emitted.
- Duplicate dirty writes to the same page collapse into one WAL frame/writeback while preserving input write evidence.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachWalTempCurrentNext65Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 79 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-attach-wal-temp-current-next.php --self-test
application-attach-wal-temp-current-next self-test passed
```

Status delta:

- `phpPass`: `23976 -> 24055` (`+79` focused PASS lines verified locally).
- Mapped upstream coverage unchanged; this is a focused behavior slice, not a new upstream denominator unit.

Non-overlap:

- Avoids accepted ATTACH temp/main WAL schema-cache and view/collation reprepare clusters through batch55.
- Avoids accepted WAL checkpoint/savepoint byte truncation, VFS file writer, rollback-journal commit/apply, and pager transaction state current-next56.
- This slice is transaction routing across attached WAL/temp schemas, not schema cache invalidation or standalone WAL byte materialization.

Dependency closure:

- No new support component is needed. The slice reuses existing lane-local PHP planning primitives and does not require external SQLite, VFS providers, or live-service credentials.
