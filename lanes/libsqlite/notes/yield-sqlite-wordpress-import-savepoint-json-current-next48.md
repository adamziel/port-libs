# Application JSON Import Savepoint Current Next48

## Behavior

This slice extends `SQLiteJsonImportSavepointPlan` for copied
`wp_options` JSON imports where a mutation explicitly inserts a missing option
row inside the active savepoint:

- `on_missing => insert` creates a bounded row with explicit or implicit
  `option_id`, `autoload`, `page_number`, and `initial_value`.
- The inserted row participates in the same statement journal, page-image, WAL
  frame, savepoint rollback, and commit plans as existing-row JSON mutations.
- If the inserted row's JSON mutation fails, statement rollback restores the
  allocated page image, discards only that statement's WAL frame, and removes
  the newly inserted row from final `wp_options` output.

## Focused Evidence

Command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonImportSavepointCurrentNext48Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 55 assertions, 0 failures
```

Regression:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonImportSavepointCurrentNext31Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 64 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-json-import-savepoint-insert-current-next48.php
```

The smoke reports `partial_rollback`, inserted `plugin_catalog` and
`theme_palette` rows, failed `insert_broken_catalog`, final option names that
exclude the failed insert, and savepoint/WAL rollback pages for only the
successful inserted rows.

## Status Delta

`phpPass` is raised by the exact new focused PASS-line delta verified locally:
`17373 -> 17428` (`+55`). Mapped upstream denominator is unchanged.

## Dependency Closure

No new external support component is needed. The slice reuses existing bounded
native PHP components: JSON mutation/JSONB/subtype handling plus
`SQLiteSavepointStack` statement journals and WAL/page-image rollback plans.

## Non-Overlap

This avoids accepted savepoint page-image rollback, WAL byte truncation,
rollback-journal/VFS application, JSON table cursor/source/visible/hidden
constraints, SELECT SQL text/JOIN/GROUP/subquery/expression ORDER BY, B-tree
page move/root collapse/overflow freelist, and VFS lock/sync/write clusters.
