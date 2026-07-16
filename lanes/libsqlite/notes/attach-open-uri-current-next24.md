# Attach Open URI Current Next24

Slice: `yield-sqlite-attach-open-uri-cluster-current-next24`

This focused slice extends SQL-form `ATTACH` execution so attached database
filenames are decoded through the existing SQLite `file:` URI parser before the
schema is registered:

- `PRAGMA database_list` stores the normalized decoded filename for attached
  URI databases.
- Attached schema record loaders receive the same normalized path and folded
  schema name.
- The ATTACH result now exposes URI and bounded open-plan metadata for
  `mode`, `cache`, `immutable`, `nolock`, `psow`, `vfs`, duplicate query
  parameters, memory databases, and malformed URI rejections.
- Bare bounded `file:/...?...` ATTACH tokens are accepted in addition to quoted
  URI string literals.

## Verification

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachOpenUriCurrentNext24Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 64 assertions, 0 failures
```

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachDetachSchemaCurrentNext18Test.php lanes/libsqlite/tests/SQLiteAttachOpenUriCurrentNext24Test.php
```

Result:

```text
Focused test run: 2 selected test files (root lock skipped)
2 test files, 144 assertions, 0 failures
```

## Delta

- `phpPass`: `8166 -> 8230` (`+64` verified PASS lines from the new focused
  test file).
- `benchmarkDenominator.mapped`: `458 -> 459` for one newly mapped focused
  ATTACH/open URI integration row.

## Non-Overlap

This avoids accepted ATTACH/DETACH schema lifecycle, ATTACH temp trigger FK
resolution, standalone file URI/open preflight, VFS file writer/lock/sync/apply
clusters, SELECT SQL text/JOIN/GROUP/subquery/ORDER/LIMIT clusters, JSON table
source/cursor/constraint clusters, WAL checkpoint/savepoint/rollback clusters,
and B-tree page-move/overflow/root-collapse clusters.

## Dependency Closure

No new support component is needed. The slice reuses existing lane-local
`SQLiteFileUri`, `SQLiteOpenPlan`, and `SQLiteAttachedSchemaCatalog` primitives
and wires them together for SQL-form ATTACH.

## Next

Wire attached database URI/open metadata into a broader native connection
lifecycle once attached database file handles are owned by a full pager.
