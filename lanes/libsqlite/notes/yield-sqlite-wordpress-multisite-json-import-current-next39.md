# Application Multisite JSON Import Current Next39

## Behavior

- Extends `SQLiteJsonImportSavepointPlan` from single-site `option_name`
  lookup to multisite `blog_id:option_name` lookup when current rows include
  `blog_id`.
- Preserves duplicate `option_name` values across different blogs while still
  rejecting duplicate `(blog_id, option_name)` rows.
- Keeps statement rollback, WAL frame indexes, page-image restore lists,
  commit pages, and failed statement evidence tied to the site-specific option
  key.
- Missing `blog_id` in a multisite mutation rolls back that statement rather
  than ambiguously mutating the first matching option name.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteMultisiteJsonImportCurrentNext39Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 55 assertions, 0 failures
```

Expected dashboard movement for this isolated lane patch: `phpPass` `14044 ->
14099` by the verified 55 new focused PASS lines. `benchmarkDenominator.mapped`
is unchanged because this is a Application import behavior slice, not a newly
mapped upstream SQLite inventory unit.

## Non-Overlap

This does not repeat the accepted single-site JSON import savepoint slice,
JSON table SELECT source/cursor/hidden/visible-constraint work, WAL byte
truncation, VFS writer/lock/sync, rollback-journal commit, Unicode GLOB,
overflow freelist release, SELECT SQL subquery/order/group work, or batch23
derived-table/materialization surfaces.

## Dependency Closure

No new support component is needed. The implementation reuses existing native
PHP JSON mutation, JSONB, savepoint, WAL-frame, and page-image planning
components.
