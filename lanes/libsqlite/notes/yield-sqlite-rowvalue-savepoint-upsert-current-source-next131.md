# rowvalue-savepoint-conflict-upsert-current-source-next131

## Behavior

- Adds bounded native PHP planning/execution for `INSERT ... ON CONFLICT (...) DO UPDATE SET (a,b,...) = (...) RETURNING ...` over copied row arrays.
- Preserves SQLite-style current-source savepoint behavior: successful INSERT/UPSERT RETURNING rows are yielded, but a later DO UPDATE unique violation rolls current source back to the savepoint image while retaining attempted next-source evidence.
- Covers composite Application `wp_options` uniqueness on `(blog_id, option_name)`, `excluded.column` expressions, row-value assignment arity checks, SQL NULL unique-key insertion, and secondary unique failure rollback.

## Evidence

- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueSavepointUpsertCurrentSourceNext131Test.php`
- Example smoke: `php lanes/libsqlite/examples/application-rowvalue-savepoint-upsert-current-source-next131.php`
- PHP lint: changed PHP files under `lanes/libsqlite/src`, `tests`, and `examples`
- Whitespace: `git diff --check -- lanes/libsqlite`

## Non-Overlap

This does not repeat accepted next126/next128 row-value UPDATE/DELETE conflict behavior, trigger UPSERT RETURNING savepoint rollback/release, savepoint page-image/WAL byte rollback, or window row-value UPSERT. The new surface is INSERT ON CONFLICT DO UPDATE row-value assignment inside a current-source savepoint with secondary unique failure rollback.

## Dependency Closure

No new support component is needed. This composes lane-local SQL row parsing, row-array current/next source mutation, and savepoint rollback evidence; it does not require ext/sqlite or a new VFS/pager dependency.
