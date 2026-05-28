# WordPress multisite JSON WAL import current-next54

## Scope

- Added `SQLiteWordPressMultisiteJsonWalImportPlan` for bounded WordPress multisite JSON import staging over WAL.
- The planner routes blog option rows and network sitemeta rows into distinct keys/tables, validates JSON-bearing WordPress option values, tracks current/next savepoint release state, and emits deterministic WAL frame/page metadata per affected multisite scope.
- This is intentionally separate from accepted single-site schema JSON savepoint/WAL imports, WAL byte truncation, JSON table cursor/source dispatch, VFS writer/apply, rollback-journal application, page relocation, and grouped/ORDER SELECT text clusters.

## Focused evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteWordPressMultisiteJsonWalImportCurrentNext54Test.php
Focused test run: 1 selected test files (root lock skipped)
55 PASS lines
1 test files, 85 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/wordpress-multisite-json-wal-import-current-next54.php
```

The smoke reports released current-blog and network batches, an open next-blog preview, WAL frame count, final keys, and released keys without requiring ext/sqlite.

## Dependency closure

No new support component is needed. The slice reuses existing lane-local JSON extraction/validity, JSONB blob, and JSON subtype primitives plus the established WordPress WAL/savepoint planning model.

## Next

Use this current-next54 planner as the WordPress multisite import bridge for later pager/VFS transaction application or parser-level import execution. Avoid duplicating it with another single-site schema JSON WAL wrapper.
